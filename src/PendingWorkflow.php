<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Support\Str;
use Sassnowski\LaravelWorkflow\Graph\DependencyGraph;

class PendingWorkflow
{
    private array $initialJobs;
    private array $jobs = [];
    private DependencyGraph $graph;

    public function __construct(array $initialJobs)
    {
        $this->initialJobs = $initialJobs;
        collect($initialJobs)->each(function ($job) {
            return $this->addJob($job, []);
        });
        $this->graph = new DependencyGraph();
    }

    public function addJob($job, array $dependencies, ?string $name = null): self
    {
        if (count($dependencies) > 0) {
            $this->graph->addDependantJob($job, $dependencies);
        }

        $this->jobs[] = [
            'job' => $job,
            'name' => $name ?: get_class($job),
        ];

        return $this;
    }

    public function start(): void
    {
        $workflow = Workflow::create([
            'job_count' => $this->jobCount(),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
        ]);

        foreach ($this->jobs as $job) {
            $job['job']
                ->withWorkflowId($workflow->id)
                ->withStepId(Str::orderedUuid())
                ->withDependantJobs($this->graph->getDependantJobs($job['job']))
                ->withDependencies($this->graph->getDependencies($job['job']));
        }

        $workflow->addJobs($this->jobs);

        $workflow->start($this->initialJobs);
    }

    public function jobCount(): int
    {
        return count($this->jobs);
    }
}
