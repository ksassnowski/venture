<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

trait WorkflowStep
{
    public array $dependantJobs = [];
    public array $dependencies = [];
    public ?int $workflowId = null;
    public ?int $stepId = null;

    public function withWorkflowId(int $workflowId): self
    {
        $this->workflowId = $workflowId;

        return $this;
    }

    public function workflow(): ?Workflow
    {
        if ($this->workflowId === null) {
            return null;
        }

        return Workflow::find($this->workflowId);
    }

    public function withDependantJobs(array $jobs): self
    {
        $this->dependantJobs = $jobs;

        return $this;
    }

    public function withDependencies(array $jobNames): self
    {
        $this->dependencies = $jobNames;

        return $this;
    }
}
