<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Events\Dispatcher;

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

        $this->withWorkflowJob($event, function (object $jobInstance) {
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

        $this->withWorkflowJob($event, function (object $jobInstance) use ($event) {
            $jobInstance
                ->workflow()
                ?->onStepFailed($jobInstance, $event->exception);
        });
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $this->withWorkflowJob($event, function (object $jobInstance) use ($event) {
            if ($jobInstance->workflow()?->isCancelled()) {
                $event->job->delete();
            }
        });
    }

    /**
     * @param Closure(object): void $callback
     */
    private function withWorkflowJob(
        JobProcessing | JobProcessed | JobFailed $event,
        Closure $callback,
    ): void {
        $jobInstance = $this->jobExtractor->extractWorkflowJob($event->job);

        if ($jobInstance !== null) {
            $callback($jobInstance);
        }
    }
}
