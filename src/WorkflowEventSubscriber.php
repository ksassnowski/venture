<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use function class_uses_recursive;

class WorkflowEventSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            JobProcessed::class,
            'Sassnowski\Venture\WorkflowEventSubscriber@handleJobProcessed',
        );

        $events->listen(
            JobFailed::class,
            'Sassnowski\Venture\WorkflowEventSubscriber@handleJobFailed',
        );

        $events->listen(
            JobProcessing::class,
            'Sassnowski\Venture\WorkflowEventSubscriber@onJobProcessing',
        );
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        if ($event->job->isReleased()) {
            return;
        }

        $this->withJobInstance($event, function (WorkflowStepInterface $jobInstance): void {
            optional($jobInstance->workflow())->onStepFinished($jobInstance);
        });
    }

    public function handleJobFailed(JobFailed $event): void
    {
        if (!$event->job->hasFailed()) {
            return;
        }

        $this->withJobInstance($event, function (WorkflowStepInterface $jobInstance) use ($event): void {
            optional($jobInstance->workflow())->onStepFailed($jobInstance, $event->exception);
        });
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $this->withJobInstance($event, function (WorkflowStepInterface $jobInstance) use ($event): void {
            if (optional($jobInstance->workflow())->isCancelled()) {
                $event->job->delete();
            }
        });
    }

    /**
     * @param Closure(WorkflowStepInterface): void $callback
     */
    private function withJobInstance(
        JobProcessing|JobFailed|JobProcessed $event,
        Closure $callback,
    ): void {
        $jobInstance = $this->getJobInstance($event->job);

        if (null !== $jobInstance) {
            $callback($jobInstance);
        }
    }

    private function getJobInstance(Job $job): ?WorkflowStepInterface
    {
        $job = \unserialize($job->payload()['data']['command']);

        // First, we want to check if we're dealing with a job that
        // already implements the correct interface. If so, we simply
        // return it.
        if ($job instanceof WorkflowStepInterface) {
            return $job;
        }

        // Next, we want to check if we're dealing with a legacy job, i.e. a
        // job that uses the old `WorkflowStep` trait. In this case, we
        // want to wrap it with the legacy adapter so we can deal with the
        // same interface from this point forward.
        if ($this->isLegacyStep($job)) {
            return LegacyWorkflowStepAdapter::from($job);
        }

        // Otherwise we're not dealing with a workflow job at all.
        return null;
    }

    private function isLegacyStep(object $job): bool
    {
        $uses = class_uses_recursive($job);

        return \in_array(WorkflowStep::class, $uses, true);
    }
}
