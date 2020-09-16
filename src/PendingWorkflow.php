<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Sassnowski\LaravelWorkflow\Graph\DependencyGraph;

class PendingWorkflow
{
    private array $initialJobs;
    private DependencyGraph $graph;

    public function __construct(array $initialJobs)
    {
        $this->initialJobs = $initialJobs;
        $this->graph = new DependencyGraph();
    }

    public function addJob($job, array $dependencies): self
    {
        foreach ($dependencies as $dep) {
            $this->graph->addDependency($job, $dep);
        }

        return $this;
    }

    public function build(): Workflow
    {
        return new Workflow($this->initialJobs, $this->graph);
    }
}
