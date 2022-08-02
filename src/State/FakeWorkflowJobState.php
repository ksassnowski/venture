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

/**
 * @internal
 */
final class FakeWorkflowJobState implements WorkflowJobStateInterface
{
    public function __construct(
        public bool $finished = false,
        public bool $failed = false,
        public ?Throwable $exception = null,
        public bool $processing = false,
        public bool $pending = true,
        public bool $gated = false,
        public bool $canRun = false,
        public bool $transitioned = false,
    ) {
    }

    public function hasFinished(): bool
    {
        return $this->finished;
    }

    public function markAsFinished(): void
    {
        $this->finished = true;

        $this->pending = false;
        $this->processing = false;
        $this->gated = false;
        $this->failed = false;
        $this->exception = null;
    }

    public function hasFailed(): bool
    {
        return $this->failed;
    }

    public function markAsFailed(Throwable $exception): void
    {
        $this->failed = true;
        $this->exception = $exception;

        $this->pending = false;
        $this->processing = false;
        $this->gated = false;
        $this->finished = false;
    }

    public function isProcessing(): bool
    {
        return $this->processing;
    }

    public function markAsProcessing(): void
    {
        $this->processing = true;

        $this->pending = false;
        $this->gated = false;
        $this->failed = false;
        $this->finished = false;
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function isGated(): bool
    {
        return $this->gated;
    }

    public function markAsGated(): void
    {
        $this->gated = true;

        $this->pending = false;
        $this->processing = false;
        $this->failed = false;
        $this->finished = false;
    }

    public function transition(): void
    {
        $this->transitioned = true;
    }

    public function canRun(): bool
    {
        return $this->canRun;
    }
}
