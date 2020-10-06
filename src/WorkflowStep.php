<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Ramsey\Uuid\UuidInterface;

trait WorkflowStep
{
    public array $dependantJobs = [];
    public array $dependencies = [];
    public ?int $workflowId = null;
    public ?UuidInterface $stepId = null;

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

    public function withStepId(UuidInterface $uuid)
    {
        $this->stepId = $uuid;

        return $this;
    }

    public function step(): ?WorkflowJob
    {
        if ($this->stepId === null) {
            return null;
        }

        return WorkflowJob::where('uuid', $this->stepId)->first();
    }
}
