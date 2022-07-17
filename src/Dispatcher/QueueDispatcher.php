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

namespace Sassnowski\Venture\Dispatcher;

use Illuminate\Support\Collection;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Venture;
use Sassnowski\Venture\WorkflowStepInterface;

final class QueueDispatcher implements JobDispatcher
{
    /**
     * @param array<int, WorkflowStepInterface> $jobs
     */
    public function dispatch(array $jobs): void
    {
        $uuids = collect($jobs)
            ->map(fn (WorkflowStepInterface $step) => $step->getStepId())
            ->filter()
            ->all();

        $this->dispatchJobs($uuids);
    }

    public function dispatchDependentJobs(WorkflowStepInterface $step): void
    {
        $this->dispatchJobs($step->getDependantJobs());
    }

    /**
     * @param array<int, string> $stepIDs
     */
    private function dispatchJobs(array $stepIDs): void
    {
        $this->getJobModels($stepIDs)
            ->each(fn (WorkflowJob $job) => $job->transition())
            ->filter(fn (WorkflowJob $job): bool => $job->canRun())
            ->each(fn (WorkflowJob $job) => $job->start());
    }

    /**
     * @param array<int, string> $stepIDs
     *
     * @return Collection<int, WorkflowJob>
     */
    private function getJobModels(array $stepIDs): Collection
    {
        return \app(Venture::$workflowJobModel)
            ->newQuery()
            ->whereIn('uuid', $stepIDs)
            ->with('workflow')
            ->get();
    }
}
