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
use PHPUnit\Framework\Assert as PHPUnit;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManagerFake implements WorkflowManagerInterface
{
    private array $started = [];
    private WorkflowManager $manager;

    public function __construct(WorkflowManager $manager)
    {
        $this->manager = $manager;
    }

    public function define(string $workflowName): WorkflowDefinition
    {
        return $this->manager->define($workflowName);
    }

    public function startWorkflow(AbstractWorkflow $abstractWorkflow): Workflow
    {
        $pendingWorkflow = $abstractWorkflow->definition();

        [$workflow, $initialBatch] = $pendingWorkflow->build(
            Closure::fromCallable([$abstractWorkflow, 'beforeCreate']),
        );

        $this->started[\get_class($abstractWorkflow)] = $abstractWorkflow;

        return $workflow;
    }

    public function hasStarted(string $workflowClass, ?callable $callback = null): bool
    {
        if (!\array_key_exists($workflowClass, $this->started)) {
            return false;
        }

        if (null === $callback) {
            return true;
        }

        return $callback($this->started[$workflowClass]);
    }

    public function assertStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowDefinition, $callback),
            "The expected workflow [{$workflowDefinition}] was not started.",
        );
    }

    public function assertNotStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->hasStarted($workflowDefinition, $callback),
            "The unexpected [{$workflowDefinition}] workflow was started.",
        );
    }
}
