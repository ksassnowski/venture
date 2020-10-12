<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use function class_uses_recursive;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class WorkflowEventSubscriber
{
    public function subscribe($events)
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
        $jobInstance = $this->getJobInstance($event->job);

        if ($this->isWorkflowStep($jobInstance)) {
            optional($jobInstance->workflow())->onStepFinished($jobInstance);
        }
    }

    public function handleJobFailed(JobFailed $event): void
    {
        if (!$event->job->hasFailed()) {
            return;
        }

        $jobInstance = $this->getJobInstance($event->job);

        if ($this->isWorkflowStep($jobInstance)) {
            optional($jobInstance->workflow())->onStepFailed($jobInstance, $event->exception);
        }
    }

    public function onJobProcessing(JobProcessing $event): void
    {
        $jobInstance = $this->getJobInstance($event->job);

        if (!$this->isWorkflowStep($jobInstance)) {
            return;
        }

        if (optional($jobInstance->workflow())->isCancelled()) {
            $event->job->delete();
        }
    }

    private function isWorkflowStep($job): bool
    {
        $uses = class_uses_recursive($job);

        return in_array(WorkflowStep::class, $uses);
    }

    private function getJobInstance(Job $job)
    {
        return unserialize($job->payload()['data']['command']);
    }
}
