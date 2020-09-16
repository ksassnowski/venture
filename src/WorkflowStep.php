<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Container\Container;

trait WorkflowStep
{
    public array $dependantJobs = [];
    public array $dependencies = [];
    public ?string $workflowId = null;

    public function withWorkflowId(string $workflowId): self
    {
        $this->workflowId = $workflowId;

        return $this;
    }

    public function workflow(): ?Workflow
    {
        if ($this->workflowId === null) {
            return null;
        }

        return Container::getInstance()->get(WorkflowRepository::class)->find($this->workflowId);
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
