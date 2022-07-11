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

final class WorkflowStepAdapter implements WorkflowStepInterface
{
    private function __construct(private object $job)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->job->{$name};
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
        return $this->job->withWorkflowId($workflowID);
    }

    public function workflow(): ?Workflow
    {
        return $this->job->workflow();
    }

    public function withDependantJobs(array $jobs): WorkflowStepInterface
    {
        return $this->job->withDependantJobs($jobs);
    }

    public function getDependantJobs(): array
    {
        return $this->job->getDependantJobs();
    }

    public function withDependencies(array $jobNames): WorkflowStepInterface
    {
        return $this->job->withDependencies($jobNames);
    }

    public function getDependencies(): array
    {
        return $this->job->getDependencies();
    }

    public function withJobId(string $jobID): WorkflowStepInterface
    {
        return $this->job->withJobId($jobID);
    }

    public function getJobId(): string
    {
        return $this->job->getJobId();
    }

    public function withStepId(UuidInterface $stepID): WorkflowStepInterface
    {
        return $this->job->withStepId($stepID);
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
        return $this->job->withName($name);
    }

    public function getName(): string
    {
        return $this->job->getName();
    }

    public function withDelay(mixed $delay): WorkflowStepInterface
    {
        return $this->job->withDelay($delay);
    }

    public function getDelay(): mixed
    {
        return $this->job->getDelay();
    }
}
