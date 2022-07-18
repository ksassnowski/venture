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

use Sassnowski\Venture\Events\WorkflowCreated;

final class SetWorkflowJobProperties
{
    public function __invoke(WorkflowCreated $event): void
    {
        $graph = $event->definition->graph();

        foreach ($event->definition->jobs() as $jobID => $job) {
            $job
                ->withWorkflowId($event->model->id)
                ->withDependantJobs($graph->getDependantJobs($jobID))
                ->withDependencies($graph->getDependencies($jobID));
        }
    }
}
