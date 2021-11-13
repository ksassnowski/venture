<?php declare(strict_types=1);

use Illuminate\Support\Str;
use Sassnowski\Venture\JobCollection;
use Sassnowski\Venture\JobDefinition;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

function createWorkflow(array $attributes = []): Workflow
{
    return Workflow::create(array_merge([
        'job_count' => 0,
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'finished_jobs' => [],
    ], $attributes));
}

function createWorkflowJob(Workflow $workflow, array $attributes = []): WorkflowJob
{
    return WorkflowJob::create(array_merge([
        'uuid' => (string) Str::orderedUuid(),
        'name' => '::name::',
        'job' => '::job::',
        'workflow_id' => $workflow->id,
        'failed_at' => null,
        'finished_at' => null,
    ], $attributes));
}

/**
 * @param object[] $jobs
 */
function wrapJobsForWorkflow(array $jobs): JobCollection
{
    return collect($jobs)->reduce(function (JobCollection $collection, object $job) {
        if ($job->jobId === null) {
            $job->jobId = get_class($job);
        }

        $definition = new JobDefinition($job->jobId, get_class($job), $job);

        $collection->add($definition);

        return $collection;
    }, new JobCollection());
}
