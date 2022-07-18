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

namespace Sassnowski\Venture\Listeners;

use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\StepIdGenerator;

final class ConfigureNestedWorkflow
{
    public function __construct(private StepIdGenerator $stepIdGenerator)
    {
    }

    public function __invoke(WorkflowAdding $event): void
    {
        if (!$event->workflowID) {
            $event->workflowID = $this->stepIdGenerator->generateId(
                $event->nestedDefinition->workflow(),
            );
        }

        foreach ($event->nestedDefinition->jobs() as $jobID => $job) {
            $job->withJobId($event->workflowID . '.' . $jobID);
        }
    }
}
