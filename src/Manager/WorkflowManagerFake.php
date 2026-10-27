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

use Illuminate\Support\Traits\ReflectsClosures;
use PHPUnit\Framework\Assert as PHPUnit;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManagerFake implements WorkflowManagerInterface
{
    use ReflectsClosures;

    /**
     * @var array<class-string<AbstractWorkflow>, array<int, array{workflow: AbstractWorkflow, connection: null|string}>>
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
            \Closure::fromCallable([$abstractWorkflow, 'beforeCreate']),
        );

        $this->started[$abstractWorkflow::class][] = [
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

        foreach ($this->started[$workflowClass] as $started) {
            if ($callback($started['workflow'], $started['connection'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param class-string<AbstractWorkflow>|\Closure(AbstractWorkflow, ?string): bool $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool                           $callback
     */
    public function assertStarted(\Closure|string $workflowClass, ?callable $callback = null): void
    {
        if ($workflowClass instanceof \Closure) {
            $callback = $workflowClass;

            /** @var class-string<AbstractWorkflow> $workflowClass */
            $workflowClass = $this->firstClosureParameterType($callback);
        }

        PHPUnit::assertTrue(
            $this->hasStarted($workflowClass, $callback),
            "The expected workflow [{$workflowClass}] was not started.",
        );
    }

    /**
     * @param class-string<AbstractWorkflow>|\Closure(AbstractWorkflow, ?string): bool $workflowClass
     * @param null|callable(AbstractWorkflow, ?string): bool                           $callback
     */
    public function assertNotStarted(\Closure|string $workflowClass, ?callable $callback = null): void
    {
        if ($workflowClass instanceof \Closure) {
            $callback = $workflowClass;

            /** @var class-string<AbstractWorkflow> $workflowClass */
            $workflowClass = $this->firstClosureParameterType($callback);
        }

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

        $actualConnections = [];

        foreach ($this->started[$workflowClass] as $started) {
            if ($started['connection'] === $connection) {
                return;
            }

            $actualConnections[] = $started['connection'];
        }

        PHPUnit::fail(
            \count($actualConnections) > 1
                ? "The workflow [{$workflowClass}] was started, but on unexpected connections [" . \implode(', ', $actualConnections) . '].'
                : "The workflow [{$workflowClass}] was started, but on unexpected connection [{$actualConnections[0]}]",
        );
    }
}
