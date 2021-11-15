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

namespace Sassnowski\Venture\DTO;

use Sassnowski\Venture\Collection\WorkflowJobCollection;

final class Workflow
{
    /** @readonly */
    public string $id;

    /** @readonly */
    public int $jobCount;

    /** @readonly */
    public int $failedJobsCount;

    /** @readonly */
    public int $processedJobsCount;

    /**
     * @readonly
     * @var string[]
     */
    private array $processedJobs;

    private WorkflowJobCollection $jobs;

    /**
     * @param WorkflowJob[] $jobs
     * @param string[] $processedJobs
     */
    public function __construct(
        string $id,
        int $jobCount,
        int $failedJobsCount,
        int $processedJobsCount,
        array $processedJobs,
        array $jobs,
    ) {
        $this->id = $id;
        $this->jobs = new WorkflowJobCollection($jobs);
        $this->jobCount = $jobCount;
        $this->failedJobsCount = $failedJobsCount;
        $this->processedJobsCount = $processedJobsCount;
        $this->processedJobs = $processedJobs;
    }

    /**
     * @return WorkflowJob[]
     */
    public function failedJobs(): array
    {
        return $this->jobs->failedJobs();
    }
}
