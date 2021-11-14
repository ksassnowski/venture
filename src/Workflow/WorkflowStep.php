<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Workflow;

use DateInterval;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

abstract class WorkflowStep implements WorkflowStepInterface
{
    use Queueable;

    /**
     * @var string[]
     */
    private array $dependantJobs = [];

    /**
     * @var string[]
     */
    private array $dependencies = [];
    private ?int $workflowId = null;
    private ?UuidInterface $stepId = null;
    private ?string $jobId = null;

    final public function withWorkflowId(int $workflowId): self
    {
        $this->workflowId = $workflowId;

        return $this;
    }

    final public function workflow(): ?Workflow
    {
        if (null === $this->workflowId) {
            return null;
        }

        return Workflow::find($this->workflowId);
    }

    /**
     * @param WorkflowStepInterface[] $jobs
     */
    final public function withDependantJobs(array $jobs): self
    {
        $this->dependantJobs = \array_map(
            fn (WorkflowStepInterface $step) => (string) $step->getStepId(),
            $jobs,
        );

        return $this;
    }

    final public function getDependantJobs(): array
    {
        return $this->dependantJobs;
    }

    /**
     * @param string[] $jobNames
     */
    final public function withDependencies(array $jobNames): self
    {
        $this->dependencies = $jobNames;

        return $this;
    }

    /**
     * @return string[]
     */
    final public function getDependencies(): array
    {
        return $this->dependencies;
    }

    final public function withStepId(UuidInterface $uuid): self
    {
        $this->stepId = $uuid;

        return $this;
    }

    final public function getStepId(): ?UuidInterface
    {
        return $this->stepId;
    }

    final public function withJobId(string $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    final public function getJobId(): ?string
    {
        return $this->jobId;
    }

    final public function step(): ?WorkflowJob
    {
        if (null === $this->stepId) {
            return null;
        }

        return WorkflowJob::where('uuid', $this->stepId)->first();
    }

    final public function withDelay(DateInterval|DateTimeInterface|int|null $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    final public function getDelay(): DateInterval|DateTimeInterface|int|null
    {
        return $this->delay;
    }
}
