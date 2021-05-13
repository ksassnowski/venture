<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Support\Arr;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

trait WorkflowStep
{
    public array $dependantJobs = [];
    public array $dependencies = [];
    public ?int $workflowId = null;
    public ?string $stepId = null;
    public ?string $jobId = null;

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
        $this->dependantJobs = Arr::pluck($jobs, 'stepId');

        return $this;
    }

    public function withDependencies(array $jobNames): self
    {
        $this->dependencies = $jobNames;

        return $this;
    }

    public function withStepId(UuidInterface $uuid): self
    {
        $this->stepId = (string) $uuid;

        return $this;
    }

    public function withJobId(string $jobId): self
    {
        $this->jobId = $jobId;

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
