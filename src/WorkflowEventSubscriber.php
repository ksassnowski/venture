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
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class WorkflowEventSubscriber
{
    public function __construct(private JobExtractor $jobExtractor)
    {
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

        $this->withWorkflowJob($event, function (object $jobInstance): void {
            $jobInstance
                ->workflow()
                ?->onStepFinished($jobInstance);
        });
    }

    public function handleJobFailed(JobFailed $event): void
    {
        if (!$event->job->hasFailed()) {
            return;
        }

        $this->withWorkflowJob($event, function (object $jobInstance) use ($event): void {
            $jobInstance
                ->workflow()
                ?->onStepFailed($jobInstance, $event->exception);
        });
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $this->withWorkflowJob($event, function (object $jobInstance) use ($event): void {
            if ($jobInstance->workflow()?->isCancelled()) {
                $event->job->delete();
            }
        });
    }

    /**
     * @param Closure(object): void $callback
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
