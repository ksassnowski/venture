<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Illuminate\Bus\Queueable;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

trait WorkflowStep
{
    use Queueable;

    /**
     * @var array<int, string>
     */
    public array $dependantJobs = [];

    /**
     * @var array<int, string>
     */
    public array $dependencies = [];

    public ?int $workflowId = null;

    public ?string $stepId = null;

    public ?string $jobId = null;

    public ?string $name = null;

    public bool $gated = false;

    public function withWorkflowId(int $workflowId): self
    {
        $this->workflowId = $workflowId;

        return $this;
    }

    public function workflow(): ?Workflow
    {
        if (null === $this->workflowId) {
            return null;
        }

        return app(Venture::$workflowModel)->find($this->workflowId);
    }

    /**
     * @param array<int, WorkflowStepInterface> $jobs
     */
    public function withDependantJobs(array $jobs): self
    {
        $this->dependantJobs = collect($jobs)
            ->map(fn (WorkflowStepInterface $job) => $job->getStepId())
            ->filter()
            ->all();

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDependantJobs(): array
    {
        return $this->dependantJobs;
    }

    /**
     * @param array<int, string> $jobNames
     */
    public function withDependencies(array $jobNames): self
    {
        $this->dependencies = $jobNames;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function withStepId(UuidInterface $uuid): self
    {
        $this->stepId = (string) $uuid;

        return $this;
    }

    public function getStepId(): ?string
    {
        return $this->stepId;
    }

    public function step(): ?WorkflowJob
    {
        if (null === $this->stepId) {
            return null;
        }

        return app(Venture::$workflowJobModel)
            ->where('uuid', $this->stepId)
            ->first();
    }

    public function withJobId(string $jobId): self
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getJobId(): string
    {
        return $this->jobId ?: static::class;
    }

    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?: static::class;
    }

    /**
     * @param Delay $delay
     */
    public function withDelay(mixed $delay): self
    {
        return $this->delay($delay);
    }

    /**
     * @return Delay
     */
    public function getDelay(): mixed
    {
        return $this->delay;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function withGate(bool $gated = true): self
    {
        $this->gated = true;

        return $this;
    }

    public function isGated(): bool
    {
        return $this->gated;
    }
}
