<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Dispatcher\JobDispatcher;
use Sassnowski\Venture\Events\WorkflowStarted;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManager implements WorkflowManagerInterface
{
    public function __construct(private JobDispatcher $dispatcher)
    {
    }

    public function define(AbstractWorkflow $workflow, string $workflowName): WorkflowDefinition
    {
        return new WorkflowDefinition($workflow, $workflowName);
    }

    public function startWorkflow(
        AbstractWorkflow $abstractWorkflow,
        ?string $connection = null,
    ): Workflow {
        $definition = $abstractWorkflow->getDefinition();

        if (null !== $connection) {
            $definition->allOnConnection($connection);
        }

        [$workflow, $initialJobs] = $definition->build(
            \Closure::fromCallable([$abstractWorkflow, 'beforeCreate']),
        );

        $this->dispatcher->dispatch($initialJobs);

        event(new WorkflowStarted($abstractWorkflow, $workflow, $initialJobs));

        return $workflow;
    }
}
