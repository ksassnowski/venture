<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\State;

use Sassnowski\Venture\Models\WorkflowJob;

class DefaultWorkflowJobState implements WorkflowJobState
{
    public function __construct(protected WorkflowJob $job)
    {
    }

    public function hasFinished(): bool
    {
        return null !== $this->job->finished_at;
    }

    public function markAsFinished(): void
    {
        $this->job->update([
            'finished_at' => now(),
            'exception' => null,
            'failed_at' => null,
        ]);
    }

    public function hasFailed(): bool
    {
        return !$this->hasFinished() && null !== $this->job->failed_at;
    }

    public function markAsFailed(\Throwable $exception): void
    {
        $this->job->update([
            'failed_at' => now(),
            'exception' => (string) $exception,
        ]);
    }

    public function isProcessing(): bool
    {
        return !$this->hasFinished()
            && !$this->hasFailed()
            && null !== $this->job->started_at;
    }

    public function markAsProcessing(): void
    {
        $this->job->update([
            'finished_at' => null,
            'failed_at' => null,
            'exception' => null,
            'started_at' => now(),
        ]);
    }

    public function isPending(): bool
    {
        return !$this->isProcessing() && !$this->hasFailed() && !$this->hasFinished();
    }

    public function isGated(): bool
    {
        if (!$this->job->gated) {
            return false;
        }

        if ($this->hasFinished() || $this->hasFailed() || $this->isProcessing()) {
            return false;
        }

        return null !== $this->job->gated_at;
    }

    public function markAsGated(): void
    {
        if (!$this->job->gated) {
            throw new \RuntimeException('Only gated jobs can be marked as gated');
        }

        $this->job->update([
            'gated_at' => now(),
        ]);
    }

    public function transition(): void
    {
        if ($this->job->gated && $this->allDependenciesHaveFinished()) {
            $this->markAsGated();
        }
    }

    public function canRun(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        if ($this->isGated()) {
            return false;
        }

        return $this->allDependenciesHaveFinished();
    }

    private function allDependenciesHaveFinished(): bool
    {
        return \count(
            \array_diff(
                $this->job->step()->getDependencies(),
                $this->job->workflow->finished_jobs,
            ),
        ) === 0;
    }
}
