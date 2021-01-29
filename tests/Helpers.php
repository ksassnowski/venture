<?php declare(strict_types=1);

use Illuminate\Support\Str;
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

function getPropertyValue($object, $property)
{
    $reflection = new \ReflectionClass($object);
    $property = $reflection->getProperty($property);
    $property->setAccessible(true);

    return $property->getValue($object);
}
