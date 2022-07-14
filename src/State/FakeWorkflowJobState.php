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

namespace Sassnowski\Venture\State;

use Closure;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Venture;
use Throwable;

/**
 * @internal
 */
final class FakeWorkflowJobState implements WorkflowJobState
{
    public bool $finished = false;

    public bool $failed = false;

    public ?Throwable $exception = null;

    public bool $processing = false;

    public bool $pending = true;

    public bool $gated = false;

    public bool $canRun = false;

    /**
     * @var null|array<int, string>
     */
    public ?array $transitioned = null;

    private bool $initialized = false;

    /**
     * @var array<string, Closure(FakeWorkflowJobState): void>
     */
    private static array $states = [];

    public function __construct(private WorkflowJob $job)
    {
    }

    /**
     * @param array<string, Closure(FakeWorkflowJobState): void> $setupFunctions
     */
    public static function setup(array $setupFunctions = []): void
    {
        Venture::useWorkflowJobState(self::class);

        foreach ($setupFunctions as $jobID => $setupFn) {
            self::$states[$jobID] = $setupFn;
        }
    }

    public static function restore(): void
    {
        self::$states = [];
        Venture::useWorkflowJobState(DefaultWorkflowJobState::class);
    }

    public function hasFinished(): bool
    {
        $this->init();

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
        $this->init();

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
        $this->init();

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
        $this->init();

        return $this->pending;
    }

    public function isGated(): bool
    {
        $this->init();

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

    public function transition(array $completedJobs): void
    {
        $this->init();

        $this->transitioned = $completedJobs;
    }

    public function canRun(): bool
    {
        $this->init();

        return $this->canRun;
    }

    private function init(): void
    {
        if ($this->initialized) {
            return;
        }

        if (null === $this->job->job) {
            return;
        }

        $jobID = $this->job->step()->getJobId();

        if (!isset(self::$states[$jobID])) {
            return;
        }

        $this->initialized = true;

        self::$states[$jobID]($this);
    }
}
