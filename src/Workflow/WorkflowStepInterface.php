<?php declare(strict_types=1);

namespace Sassnowski\Venture\Workflow;

use DateInterval;
use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Illuminate\Contracts\Queue\ShouldQueue;

interface WorkflowStepInterface extends ShouldQueue
{
    public function withWorkflowId(int $workflowId): self;

    public function workflow(): ?Workflow;

    /**
     * @param WorkflowStepInterface[] $jobs
     */
    public function withDependantJobs(array $jobs): self;

    /**
     * @return string[]
     */
    public function getDependantJobs(): array;

    /**
     * @param string[] $jobNames
     */
    public function withDependencies(array $jobNames): self;

    /**
     * @return string[]
     */
    public function getDependencies(): array;

    public function withStepId(UuidInterface $uuid): self;

    public function getStepId(): ?UuidInterface;

    public function withJobId(string $jobId): self;

    public function getJobId(): ?string;

    public function step(): ?WorkflowJob;

    public function withDelay(DateTimeInterface | DateInterval | int | null $delay): self;

    public function getDelay(): DateInterval | DateTimeInterface | int | null;
}
