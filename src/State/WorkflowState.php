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

interface WorkflowState
{
    public function markJobAsFinished(WorkflowableJob $job): void;

    public function markJobAsFailed(WorkflowableJob $job, Throwable $exception): void;

    public function allJobsHaveFinished(): bool;

    public function isFinished(): bool;

    public function markAsFinished(): void;

    public function isCancelled(): bool;

    public function markAsCancelled(): void;

    public function remainingJobs(): int;

    public function hasRan(): bool;
}
