<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Illuminate\Contracts\Queue\Job;

interface JobExtractor
{
    /**
     * Extracts a workflow job instance from a queue job. Should return
     * null if the job instance is not a workflow job.
     */
    public function extractWorkflowJob(Job $queueJob): ?object;
}