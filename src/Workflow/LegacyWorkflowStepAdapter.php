<?php declare(strict_types=1);

namespace Sassnowski\Venture\Workflow;

use DateInterval;
use Ramsey\Uuid\Uuid;
use DateTimeInterface;
use function in_array;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use function class_uses_recursive;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

final class LegacyWorkflowStepAdapter implements WorkflowStepInterface
{
    private function __construct(private object $workflowStep)
    {
    }

    public static function from(object $job): WorkflowStepInterface
    {
        if ($job instanceof WorkflowStepInterface) {
            return $job;
        }

        $uses = class_uses_recursive($job);

        if (!in_array(\Sassnowski\Venture\WorkflowStep::class, $uses)) {
            throw new InvalidArgumentException(
                'Provided job uses neither the WorkflowStep trait, nor does it implement the interface itself.'
            );
        }

        return new self($job);
    }

    public function withWorkflowId(int $workflowId): WorkflowStepInterface
    {
        $this->workflowStep->withWorkflowId($workflowId);

        return $this;
    }

    public function workflow(): ?Workflow
    {
        return $this->workflowStep->workflow();
    }

    public function withDependantJobs(array $jobs): WorkflowStepInterface
    {
        $this->workflowStep->withDependantJobs($jobs);

        return $this;
    }

    public function getDependantJobs(): array
    {
        return $this->workflowStep->dependantJobs;
    }

    public function withDependencies(array $jobNames): WorkflowStepInterface
    {
        $this->workflowStep->withDependencies($jobNames);

        return $this;
    }

    /**
     * @return string[]
     */
    final public function getDependencies(): array
    {
        return $this->workflowStep->dependencies;
    }

    public function withStepId(UuidInterface $uuid): WorkflowStepInterface
    {
        $this->workflowStep->withStepId($uuid);

        return $this;
    }

    public function getStepId(): ?UuidInterface
    {
        return Uuid::fromString($this->workflowStep->stepId);
    }

    public function withJobId(string $jobId): WorkflowStepInterface
    {
        $this->workflowStep->withJobId($jobId);

        return $this;
    }

    public function getJobId(): ?string
    {
        return $this->workflowStep->jobId;
    }

    public function step(): ?WorkflowJob
    {
        return $this->workflowStep->step();
    }

    public function withDelay(DateInterval | DateTimeInterface | int | null $delay): WorkflowStepInterface
    {
        $this->workflowStep->delay = $delay;

        return $this;
    }

    public function getDelay(): DateInterval | DateTimeInterface | int | null
    {
        return $this->workflowStep->delay;
    }
}
