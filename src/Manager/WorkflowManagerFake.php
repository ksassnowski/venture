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

use Closure;
use PHPUnit\Framework\Assert as PHPUnit;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManagerFake implements WorkflowManagerInterface
{
    /**
     * @var array<class-string<AbstractWorkflow>, array{workflow: AbstractWorkflow, connection: null|string}>
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

    public function startWorkflow(
        AbstractWorkflow $abstractWorkflow,
        ?string $connection = null,
    ): Workflow {
        $pendingWorkflow = $abstractWorkflow->getDefinition();

        [$workflow, $initialBatch] = $pendingWorkflow->build(
            Closure::fromCallable([$abstractWorkflow, 'beforeCreate']),
        );

        $this->started[$abstractWorkflow::class] = [
            'workflow' => $abstractWorkflow,
            'connection' => $connection,
        ];

        return $workflow;
    }

    /**
     * @param class-string<AbstractWorkflow>                 $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool $callback
     */
    public function hasStarted(string $workflowClass, ?callable $callback = null): bool
    {
        if (!\array_key_exists($workflowClass, $this->started)) {
            return false;
        }

        if (null === $callback) {
            return true;
        }

        return $callback(
            $this->started[$workflowClass]['workflow'],
            $this->started[$workflowClass]['connection'],
        );
    }

    /**
     * @param class-string<AbstractWorkflow>                 $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool $callback
     */
    public function assertStarted(string $workflowClass, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowClass, $callback),
            "The expected workflow [{$workflowClass}] was not started.",
        );
    }

    /**
     * @param class-string<AbstractWorkflow>                 $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool $callback
     */
    public function assertNotStarted(string $workflowClass, ?callable $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->hasStarted($workflowClass, $callback),
            "The unexpected [{$workflowClass}] workflow was started.",
        );
    }

    /**
     * @param class-string<AbstractWorkflow>                 $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool $callback
     */
    public function assertStartedOnConnection(
        string $workflowClass,
        string $connection,
        ?callable $callback = null,
    ): void {
        $this->assertStarted($workflowClass, $callback);

        $actualConnection = $this->started[$workflowClass]['connection'];

        PHPUnit::assertSame(
            $connection,
            $actualConnection,
            "The workflow [{$workflowClass}] was started, but on unexpected connection [{$actualConnection}]",
        );
    }
}
