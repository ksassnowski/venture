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

use Throwable;

interface WorkflowJobStateInterface
{
    public function hasFinished(): bool;

    public function markAsFinished(): void;

    public function hasFailed(): bool;

    public function markAsFailed(Throwable $exception): void;

    public function isProcessing(): bool;

    public function markAsProcessing(): void;

    public function isPending(): bool;

    /**
     * Check if a job is currently waiting for manual confirmation
     * to be started.
     */
    public function isGated(): bool;

    public function markAsGated(): void;

    /**
     * Transition the job to the next state if applicable.
     */
    public function transition(): void;

    /**
     * Check if a job is ready to be dispatched.
     */
    public function canRun(): bool;
}
