<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use function class_uses_recursive;
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
        $jobInstance = unserialize($event->job->payload()['data']['command']);

        $uses = class_uses_recursive($jobInstance);

        if (!in_array(WorkflowStep::class, $uses)) {
            return;
        }

        optional($jobInstance->workflow())->onStepFinished($jobInstance);
    }
}
