<?php declare(strict_types=1);

namespace Sassnowski\Venture\Manager;

use Sassnowski\Venture\Models\Workflow;
use PHPUnit\Framework\Assert as PHPUnit;
use Sassnowski\Venture\WorkflowDefinition;

class WorkflowManagerFake implements WorkflowManagerInterface
{
    private array $started = [];

    public function startWorkflow(WorkflowDefinition $definition): Workflow
    {
        $pendingWorkflow = $definition->definition();

        [$workflow, $initialBatch] = $pendingWorkflow->build();

        $this->started[] = get_class($definition);

        return $workflow;
    }

    public function hasStarted(string $workflowClass): bool
    {
        return in_array($workflowClass, $this->started);
    }

    public function assertStarted(string $workflowDefinition): void
    {
        PHPUnit::assertTrue(
            $this->hasStarted($workflowDefinition),
            "The expected workflow [{$workflowDefinition}] was not started."
        );
    }
}
