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
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use function class_uses_recursive;

final class LegacyWorkflowStepAdapter implements WorkflowStepInterface
{
    /**
     * @param UsesWorkflowStepTrait $workflowStep
     */
    private function __construct(private mixed $workflowStep)
    {
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function from(object $job): WorkflowStepInterface
    {
        if ($job instanceof WorkflowStepInterface) {
            return $job;
        }

        $uses = class_uses_recursive($job);

        if (!\in_array(\Sassnowski\Venture\WorkflowStep::class, $uses, true)) {
            throw new InvalidArgumentException(
                'Provided job uses neither the WorkflowStep trait, nor does it implement the interface itself.',
            );
        }

        /** @var UsesWorkflowStepTrait $job */
        return new self($job);
    }

    public function withWorkflowId(int $workflowId): WorkflowStepInterface
    {
        $this->workflowStep->withWorkflowId($workflowId);

        return $this;
    }

    public function getWorkflowId(): ?int
    {
        return $this->workflowStep->workflowId;
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
    public function getDependencies(): array
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
        return null !== $this->workflowStep->stepId
            ? Uuid::fromString($this->workflowStep->stepId)
            : null;
    }

    public function step(): ?WorkflowJob
    {
        return $this->workflowStep->step();
    }

    public function withDelay(DateInterval|DateTimeInterface|int|null $delay): WorkflowStepInterface
    {
        $this->workflowStep->delay = $delay;

        return $this;
    }

    public function getDelay(): DateInterval|DateTimeInterface|int|null
    {
        return $this->workflowStep->delay;
    }

    /**
     * @return UsesWorkflowStepTrait
     */
    public function getWrappedJob(): object
    {
        return $this->workflowStep;
    }
}
