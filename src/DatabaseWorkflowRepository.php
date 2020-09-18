<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Support\Str;
use Illuminate\Database\Connection;

class DatabaseWorkflowRepository implements WorkflowRepository
{
    private Connection $connection;
    private string $table;

    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function find(string $workflowId): ?Workflow
    {
        $workflow = $this->connection->table($this->table)
            ->where('id', $workflowId)
            ->first();

        return new Workflow(
            $workflowId,
            $this,
            json_decode($workflow->state, true),
            $workflow->job_count,
            $workflow->jobs_processed,
            $workflow->jobs_failed,
        );
    }

    public function store(PendingWorkflow $workflow): Workflow
    {
        return Workflow::create([
            'id' => (string) Str::orderedUuid(),
            'job_count' => $workflow->jobCount(),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'state' => [],
        ]);
    }

    public function updateValues(string $workflowId, array $values)
    {
        $this->connection->table($this->table)
            ->where('id', $workflowId)
            ->lockForUpdate();

        $this->connection->transaction(function (Connection $connection) use ($workflowId, $values) {
            $connection->table($this->table)
                ->where('id', $workflowId)
                ->update($values);
        });
    }
}
