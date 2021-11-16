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
use Illuminate\Contracts\Queue\ShouldQueue;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

interface WorkflowStepInterface extends ShouldQueue
{
    public function withWorkflowId(int $workflowId): self;

    public function getWorkflowId(): ?int;

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

    public function step(): ?WorkflowJob;

    public function withDelay(DateTimeInterface|DateInterval|int|null $delay): self;

    public function getDelay(): DateInterval|DateTimeInterface|int|null;
}
