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

namespace Sassnowski\Venture;

use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

/**
 * @property Delay              $delay
 * @property array<int, string> $dependantJobs
 * @property array<int, string> $dependencies
 * @property ?string            $jobId
 * @property ?string            $name
 * @property ?string            $stepId
 * @property ?int               $workflowId
 */
final class WorkflowStepAdapter implements WorkflowStepInterface
{
    private function __construct(private object $job)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->job->{$name};
    }

    public function __set(string $name, mixed $value): void
    {
        $this->job->{$name} = $value;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->job->{$name}(...$arguments);
    }

    public static function make(object $job): WorkflowStepInterface
    {
        if ($job instanceof WorkflowStepInterface) {
            return $job;
        }

        $uses = \class_uses_recursive($job);

        if (!\in_array(WorkflowStep::class, $uses, true)) {
            throw new InvalidArgumentException('Wrapped job instance does not use WorkflowStep trait');
        }

        return new self($job);
    }

    public function withWorkflowId(int $workflowID): WorkflowStepInterface
    {
        $this->job->withWorkflowId($workflowID);

        return $this;
    }

    public function workflow(): ?Workflow
    {
        return $this->job->workflow();
    }

    public function withDependantJobs(array $jobs): WorkflowStepInterface
    {
        $this->job->withDependantJobs($jobs);

        return $this;
    }

    public function getDependantJobs(): array
    {
        return $this->job->getDependantJobs();
    }

    public function withDependencies(array $jobNames): WorkflowStepInterface
    {
        $this->job->withDependencies($jobNames);

        return $this;
    }

    public function getDependencies(): array
    {
        return $this->job->getDependencies();
    }

    public function withJobId(string $jobID): WorkflowStepInterface
    {
        $this->job->withJobId($jobID);

        return $this;
    }

    public function getJobId(): string
    {
        return $this->job->getJobId();
    }

    public function withStepId(UuidInterface $stepID): WorkflowStepInterface
    {
        $this->job->withStepId($stepID);

        return $this;
    }

    public function getStepId(): ?string
    {
        return $this->job->getStepId();
    }

    public function step(): ?WorkflowJob
    {
        return $this->job->step();
    }

    public function withName(string $name): WorkflowStepInterface
    {
        $this->job->withName($name);

        return $this;
    }

    public function getName(): string
    {
        return $this->job->getName();
    }

    public function withDelay(mixed $delay): WorkflowStepInterface
    {
        $this->job->withDelay($delay);

        return $this;
    }

    public function getDelay(): mixed
    {
        return $this->job->getDelay();
    }
}
