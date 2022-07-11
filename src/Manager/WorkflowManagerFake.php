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
    /**
     * @var array<class-string<AbstractWorkflow>, AbstractWorkflow>
     */
    private array $started = [];

    private WorkflowManagerInterface $manager;

    public function __construct(WorkflowManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    public function define(AbstractWorkflow $workflow, string $workflowName): WorkflowDefinition
    {
        return $this->manager->define($workflow, $workflowName);
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

    /**
     * @param null|callable(AbstractWorkflow): bool $callback
     */
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

    /**
     * @param null|callable(AbstractWorkflow): bool $callback
     */
    public function assertStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowDefinition, $callback),
            "The expected workflow [{$workflowDefinition}] was not started.",
        );
    }

    /**
     * @param null|callable(AbstractWorkflow): bool $callback
     */
    public function assertNotStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->hasStarted($workflowDefinition, $callback),
            "The unexpected [{$workflowDefinition}] workflow was started.",
        );
    }
}
