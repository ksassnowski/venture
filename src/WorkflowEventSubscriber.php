<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use function class_uses_recursive;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;

class WorkflowEventSubscriber
{
    public function subscribe($events)
    {
        $events->listen(
            JobProcessed::class,
            [WorkflowEventSubscriber::class, 'handleJobProcessed'],
        );

        $events->listen(
            JobFailed::class,
            [WorkflowEventSubscriber::class, 'handleJobFailed'],
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
