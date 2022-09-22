<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Sassnowski\Venture\Actions\HandlesFailedJobs;
use Sassnowski\Venture\Actions\HandlesFinishedJobs;
use function event;

class WorkflowEventSubscriber
{
    public function __construct(
        private JobExtractor $jobExtractor,
        private HandlesFinishedJobs $handleFinishedJobs,
        private HandlesFailedJobs $handleFailedJobs,
    ) {
    }

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

        if ($event->job->hasFailed()) {
            return;
        }

        $this->withWorkflowJob($event, function (WorkflowableJob $jobInstance): void {
            ($this->handleFinishedJobs)($jobInstance);

            event(new Events\JobFinished($jobInstance));
        });
    }

    public function handleJobFailed(JobFailed $event): void
    {
        if (!$event->job->hasFailed()) {
            return;
        }

        $this->withWorkflowJob($event, function (WorkflowableJob $jobInstance) use ($event): void {
            ($this->handleFailedJobs)($jobInstance, $event->exception);

            event(new Events\JobFailed($jobInstance, $event->exception));
        });
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $this->withWorkflowJob($event, function (WorkflowableJob $jobInstance) use ($event): void {
            if ($jobInstance->workflow()?->isCancelled()) {
                $event->job->delete();
            } else {
                event(new Events\JobProcessing($jobInstance));
            }
        });
    }

    /**
     * @param Closure(WorkflowableJob): void $callback
     */
    private function withWorkflowJob(
        JobProcessing|JobProcessed|JobFailed $event,
        Closure $callback,
    ): void {
        $jobInstance = $this->jobExtractor->extractWorkflowJob($event->job);

        if (null !== $jobInstance) {
            $callback($jobInstance);
        }
    }
}
