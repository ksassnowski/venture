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
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Workflow\WorkflowBuilder;
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

    public function startWorkflow(WorkflowBuilder $workflowBuilder): Workflow
    {
        $pendingWorkflow = $workflowBuilder->definition();

        [$workflow, $initialBatch] = $pendingWorkflow->build(
            Closure::fromCallable([$workflowBuilder, 'beforeCreate']),
        );

        $this->started[\get_class($workflowBuilder)] = $workflowBuilder;

        return $workflow;
    }

    /**
     * @psalm-param class-string<WorkflowBuilder> $workflowClass
     *
     * @param null|callable(WorkflowBuilder): bool $callback
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
     * @psalm-param class-string<WorkflowBuilder> $workflowClass
     *
     * @param null|callable(WorkflowBuilder): bool $callback
     */
    public function assertStarted(string $workflowClass, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowClass, $callback),
            "The expected workflow [{$workflowClass}] was not started.",
        );
    }

    /**
     * @psalm-param class-string<WorkflowBuilder> $workflowClass
     *
     * @param null|callable(WorkflowBuilder): bool $callback
     */
    public function assertNotStarted(string $workflowClass, ?callable $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->hasStarted($workflowClass, $callback),
            "The unexpected [{$workflowClass}] workflow was started.",
        );
    }
}
