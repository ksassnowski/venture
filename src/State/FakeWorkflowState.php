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

namespace Sassnowski\Venture\State;

use Sassnowski\Venture\WorkflowableJob;
use Throwable;

/**
 * @internal
 */
final class FakeWorkflowState implements WorkflowState
{
    /**
     * @param array<string, true>      $finishedJobs
     * @param array<string, Throwable> $failedJobs
     */
    public function __construct(
        public array $finishedJobs = [],
        public array $failedJobs = [],
        public bool $allJobsFinished = false,
        public bool $finished = false,
        public bool $cancelled = false,
        public int $remainingJobs = 0,
        public bool $hasRan = false,
    ) {
    }

    public function markJobAsFinished(WorkflowableJob $job): void
    {
        $this->finishedJobs[$job->getJobId()] = true;
    }

    public function markJobAsFailed(WorkflowableJob $job, Throwable $exception): void
    {
        $this->failedJobs[$job->getJobId()] = $exception;
    }

    public function allJobsHaveFinished(): bool
    {
        return $this->allJobsFinished;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function markAsFinished(): void
    {
        $this->finished = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function markAsCancelled(): void
    {
        $this->cancelled = true;
    }

    public function remainingJobs(): int
    {
        return $this->remainingJobs;
    }

    public function hasRan(): bool
    {
        return $this->hasRan;
    }
}
