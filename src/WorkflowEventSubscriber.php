<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Queue\Events\JobProcessed;

class WorkflowEventSubscriber
{
    public function subscribe($events)
    {
        $events->listen(
            JobProcessed::class,
            [WorkflowEventSubscriber::class, 'handleJobProcessed'],
        );
    }

    public function handleJobProcessed(JobProcessed $event)
    {
        // Can this break?
        $jobInstance = unserialize($event->job->payload()['data']['command']);

        $uses = class_uses($jobInstance);

        if (!in_array(WorkflowStep::class, $uses)) {
            return;
        }

        optional($jobInstance->workflow())->onStepFinished($jobInstance);
    }
}
