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

use Illuminate\Support\Facades\DB;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowableJob;
use Illuminate\Support\Facades\Redis;

class DefaultWorkflowState implements WorkflowState
{
    public function __construct(protected Workflow $workflow)
    {
    }

    public function markJobAsFinished(WorkflowableJob $job): void
    {
        Redis::funnel("workflow-{$this->workflow->getKey()}-finish")
            ->limit(1)
            ->block(5)
            ->then(function () use ($job) {
                DB::transaction(function () use ($job): void {
                    /** @var Workflow $workflow */
                    $workflow = $this->workflow
                        ->newQuery()
                        ->findOrFail($this->workflow->getKey());

                    $workflow->newQuery()
                        ->where('id', $workflow->getKey())
                        ->update([
                            'finished_jobs' => array_merge(
                                $workflow->finished_jobs,
                                [$job->getJobId()]
                            ),
                            'jobs_processed' => $workflow->jobs_processed + 1,
                        ]);

                    $job->step()?->markAsFinished();
                });
            });
    }

    public function markJobAsFailed(WorkflowableJob $job, \Throwable $exception): void
    {
        DB::transaction(function () use ($job, $exception): void {
            /** @var Workflow $workflow */
            $workflow = $this->workflow
                ->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->workflow->getKey(), ['jobs_failed']);

            $this->workflow->update([
                'jobs_failed' => $workflow->jobs_failed + 1,
            ]);

            $job->step()?->markAsFailed($exception);
        });
    }

    public function allJobsHaveFinished(): bool
    {
        return $this->workflow->job_count === $this->workflow->jobs_processed;
    }

    public function isFinished(): bool
    {
        return null !== $this->workflow->finished_at;
    }

    public function markAsFinished(): void
    {
        $this->workflow->update(['finished_at' => now()]);
    }

    public function isCancelled(): bool
    {
        return null !== $this->workflow->cancelled_at;
    }

    public function markAsCancelled(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->workflow->update(['cancelled_at' => now()]);
    }

    public function remainingJobs(): int
    {
        return $this->workflow->job_count - $this->workflow->jobs_processed;
    }

    public function hasRan(): bool
    {
        return ($this->workflow->jobs_processed + $this->workflow->jobs_failed) === $this->workflow->job_count;
    }
}
