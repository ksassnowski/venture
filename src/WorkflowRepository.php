<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

interface WorkflowRepository
{
    public function find(string $workflowId): ?Workflow;

    public function store(PendingWorkflow $workflow): Workflow;

    public function updateValues(string $workflowId, array $values);

    public function addJobs(string $workflowId, array $jobs);

    public function fetchJobs(string $workflowId): WorkflowJob;
}
