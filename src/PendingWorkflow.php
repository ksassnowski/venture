<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Graph\DependencyGraph;

class PendingWorkflow
{
    private array $jobs = [];
    private DependencyGraph $graph;
    private string $workflowName;
    private ?string $thenCallback = null;

    public function __construct(string $workflowName = '')
    {
        $this->graph = new DependencyGraph();
        $this->workflowName = $workflowName;
    }

    public function addJob($job, array $dependencies = [], ?string $name = null): self
    {
        $this->graph->addDependantJob($job, $dependencies);

        $this->jobs[] = [
            'job' => $job,
            'name' => $name ?: get_class($job),
        ];

        return $this;
    }

    public function then($callback): self
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        $this->thenCallback = serialize($callback);

        return $this;
    }

    public function start(): Workflow
    {
        $workflow = Workflow::create([
            'name' => $this->workflowName,
            'job_count' => $this->jobCount(),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
            'then_callback' => $this->thenCallback,
        ]);

        foreach ($this->jobs as $job) {
            $job['job']
                ->withWorkflowId($workflow->id)
                ->withStepId(Str::orderedUuid())
                ->withDependantJobs($this->graph->getDependantJobs($job['job']))
                ->withDependencies($this->graph->getDependencies($job['job']));
        }

        $workflow->addJobs($this->jobs);
        $workflow->start($this->graph->getJobsWithoutDependencies());

        return $workflow;
    }

    public function jobCount(): int
    {
        return count($this->jobs);
    }
}
