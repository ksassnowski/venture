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

namespace Sassnowski\Venture\Actions;

use Sassnowski\Venture\Events\WorkflowFinished;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Venture;
use Sassnowski\Venture\WorkflowStepInterface;

final class HandleFinishedJobs implements HandlesFinishedJobs
{
    public function __invoke(WorkflowStepInterface $step): void
    {
        $workflow = $step->workflow();

        if (null === $workflow) {
            return;
        }

        $workflow->markJobAsFinished($step);

        if ($workflow->isCancelled()) {
            return;
        }

        if ($workflow->allJobsHaveFinished()) {
            $workflow->markAsFinished();
            $workflow->runThenCallback();

            \event(new WorkflowFinished($workflow));

            return;
        }

        $this->dispatchReadyDependentJobs($step);
    }

    private function dispatchReadyDependentJobs(WorkflowStepInterface $step): void
    {
        if (empty($step->getDependantJobs())) {
            return;
        }

        /** @var WorkflowJob $jobModel */
        $jobModel = \app(Venture::$workflowJobModel);

        $jobModel::query()
            ->whereIn('uuid', $step->getDependantJobs())
            ->with('workflow')
            ->get()
            ->each(fn (WorkflowJob $job) => $job->transition())
            ->filter(fn (WorkflowJob $job): bool => $job->canRun())
            ->each(fn (WorkflowJob $job) => $job->start());
    }
}
