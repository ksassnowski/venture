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

use PHPUnit\Framework\Assert;
use Sassnowski\Venture\WorkflowStepInterface;

final class FakeDispatcher implements JobDispatcher
{
    /**
     * @var array<string, ?string>
     */
    private array $dispatchedJobs = [];

    /**
     * @var array<string, true>
     */
    private array $dispatchedDependentJobsForStep = [];

    /**
     * @param array<int, WorkflowStepInterface> $jobs
     */
    public function dispatch(array $jobs): void
    {
        foreach ($jobs as $job) {
            $this->dispatchedJobs[$job->getJobId()] = $job->getConnection();
        }
    }

    public function dispatchDependentJobs(WorkflowStepInterface $step): void
    {
        $this->dispatchedDependentJobsForStep[$step->getJobId()] = true;
    }

    public function assertJobWasDispatched(string $jobID, ?string $connection = null): void
    {
        Assert::assertArrayHasKey(
            $jobID,
            $this->dispatchedJobs,
            "Expected job {$jobID} was not dispatched",
        );

        if (null !== $connection) {
            $actualConnection = $this->dispatchedJobs[$jobID];

            Assert::assertSame(
                $connection,
                $this->dispatchedJobs[$jobID],
                "Expected job {$jobID} was dispatched on wrong connection {$actualConnection}",
            );
        }
    }

    public function assertJobWasNotDispatched(string $jobID): void
    {
        Assert::assertArrayNotHasKey(
            $jobID,
            $this->dispatchedJobs,
            "Unexpected job {$jobID} was dispatched",
        );
    }

    public function assertDependentJobsDispatchedFor(string $jobID): void
    {
        Assert::assertArrayHasKey(
            $jobID,
            $this->dispatchedDependentJobsForStep,
            "Dependent jobs for {$jobID} were not dispatched",
        );
    }

    public function assertDependentJobsNotDispatchedFor(string $jobID): void
    {
        Assert::assertArrayNotHasKey(
            $jobID,
            $this->dispatchedDependentJobsForStep,
            "Unexpected dependent jobs for job {$jobID} were dispatched",
        );
    }
}
