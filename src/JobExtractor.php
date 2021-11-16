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

use Illuminate\Contracts\Queue\Job;
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use function class_uses_recursive;

final class JobExtractor
{
    public function extract(Job $queueJob): ?WorkflowStepInterface
    {
        /**
         * @var string $serializedCommand
         * @psalm-suppress MixedArrayAccess
         */
        $serializedCommand = $queueJob->payload()['data']['command'];

        /** @var object $job */
        $job = \unserialize($serializedCommand);

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
