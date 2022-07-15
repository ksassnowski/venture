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

use Illuminate\Contracts\Queue\ShouldQueue;
use Ramsey\Uuid\UuidInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

interface WorkflowStepInterface extends ShouldQueue
{
    public function withWorkflowId(int $workflowID): self;

    public function workflow(): ?Workflow;

    /**
     * @param array<int, WorkflowStepInterface> $jobs
     */
    public function withDependantJobs(array $jobs): self;

    /**
     * @return array<int, string>
     */
    public function getDependantJobs(): array;

    /**
     * @param array<int, string> $jobNames
     */
    public function withDependencies(array $jobNames): self;

    /**
     * @return array<int, string>
     */
    public function getDependencies(): array;

    public function withJobId(string $jobID): self;

    public function getJobId(): string;

    public function withStepId(UuidInterface $stepID): self;

    public function getStepId(): ?string;

    public function step(): ?WorkflowJob;

    public function withName(string $name): self;

    public function getName(): string;

    /**
     * @param Delay $delay
     */
    public function withDelay(mixed $delay): self;

    /**
     * @return Delay
     */
    public function getDelay(): mixed;

    public function withConnection(?string $connection): self;

    public function getConnection(): ?string;

    public function withGate(bool $gated = true): self;

    public function isGated(): bool;
}
