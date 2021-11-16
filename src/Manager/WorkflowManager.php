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

namespace Sassnowski\Venture\Manager;

use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;
use Sassnowski\Venture\Workflow\WorkflowBuilder;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManager implements WorkflowManagerInterface
{
    private Dispatcher $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function define(string $workflowName): WorkflowDefinition
    {
        return new WorkflowDefinition($workflowName);
    }

    public function startWorkflow(WorkflowBuilder $workflowBuilder): Workflow
    {
        $definition = $workflowBuilder->definition();

        [$workflow, $initialJobs] = $definition->build(
            Closure::fromCallable([$workflowBuilder, 'beforeCreate']),
        );

        collect($initialJobs)->each(function (WorkflowStepInterface $job): void {
            if ($job instanceof LegacyWorkflowStepAdapter) {
                $job = $job->getWrappedJob();
            }

            $this->dispatcher->dispatch($job);
        });

        return $workflow;
    }
}
