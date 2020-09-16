<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Container\Container;
use Sassnowski\LaravelWorkflow\Graph\DependencyGraph;

class PendingWorkflow
{
    private array $initialJobs;
    private array $jobs;
    private DependencyGraph $graph;

    public function __construct(array $initialJobs)
    {
        $this->initialJobs = $initialJobs;
        $this->jobs = $initialJobs;
        $this->graph = new DependencyGraph();
    }

    public function addJob($job, array $dependencies): self
    {
        $this->graph->addDependantJob($job, $dependencies);

        $this->jobs[] = $job;

        return $this;
    }

    public function start(): void
    {
        /** @var Workflow $workflow */
        $workflow = Container::getInstance()->get(WorkflowRepository::class)->store($this);

        foreach ($this->jobs as $job) {
            $job
                ->withWorkflowId($workflow->getId())
                ->withDependantJobs($this->graph->getDependantJobs($job))
                ->withDependencies($this->graph->getDependencies($job));
        }

        $workflow->start($this->initialJobs);
    }

    public function jobCount(): int
    {
        return count($this->jobs);
    }
}
