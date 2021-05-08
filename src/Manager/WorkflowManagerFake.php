<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Closure;
use Sassnowski\Venture\Models\Workflow;
use PHPUnit\Framework\Assert as PHPUnit;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\WorkflowJob;
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
            Closure::fromCallable([$abstractWorkflow, 'beforeCreate'])
        );

        $this->started[get_class($abstractWorkflow)] = $abstractWorkflow;

        return $workflow;
    }

    public function completeJob(int $jobId): void
    {
        // todo
    }

    public function hasStarted(string $workflowClass, ?callable $callback = null): bool
    {
        if (!array_key_exists($workflowClass, $this->started)) {
            return false;
        }

        if ($callback === null) {
            return true;
        }

        return $callback($this->started[$workflowClass]);
    }

    public function assertStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowDefinition, $callback),
            "The expected workflow [{$workflowDefinition}] was not started."
        );
    }

    public function assertNotStarted(string $workflowDefinition, ?callable $callback = null): void
    {
        PHPUnit::assertFalse(
            $this->hasStarted($workflowDefinition, $callback),
            "The unexpected [{$workflowDefinition}] workflow was started."
        );
    }
}
