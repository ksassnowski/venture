<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use function class_uses_recursive;
use Illuminate\Contracts\Queue\Job;

final class UnserializeJobExtractor implements JobExtractor
{
    public function extractWorkflowJob(Job $queueJob): ?object
    {
        $instance = unserialize($queueJob->payload()['data']['command']);

        $uses = class_uses_recursive($instance);

        if (!in_array(WorkflowStep::class, $uses)) {
            return null;
        }

        return $instance;
    }
}