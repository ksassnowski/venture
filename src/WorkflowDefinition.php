<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Graph\DependencyGraph;

class WorkflowDefinition
{
    private array $jobs = [];
    private DependencyGraph $graph;
    private string $workflowName;
    private ?string $thenCallback = null;
    private ?string $catchCallback = null;

    public function __construct(string $workflowName = '')
    {
        $this->graph = new DependencyGraph();
        $this->workflowName = $workflowName;
    }

    /**
     * @param  object                                  $job
     * @param  array                                   $dependencies
     * @param  string|null                             $name
     * @param  DateTimeInterface|DateInterval|int|null $delay
     * @return $this
     */
    public function addJob($job, array $dependencies = [], ?string $name = null, $delay = null): self
    {
        $this->graph->addDependantJob($job, $dependencies);

        if ($delay !== null) {
            $job->delay($delay);
        }

        $this->jobs[] = [
            'job' => $job,
            'name' => $name ?: get_class($job),
        ];

        return $this;
    }

    public function then($callback): self
    {
        $this->thenCallback = $this->serializeCallback($callback);

        return $this;
    }

    public function catch($callback): self
    {
        $this->catchCallback = $this->serializeCallback($callback);

        return $this;
    }

    public function build(): array
    {
        $workflow = Workflow::create([
            'name' => $this->workflowName,
            'job_count' => count($this->jobs),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
            'then_callback' => $this->thenCallback,
            'catch_callback' => $this->catchCallback,
        ]);

        foreach ($this->jobs as $job) {
            $job['job']
                ->withWorkflowId($workflow->id)
                ->withStepId(Str::orderedUuid())
                ->withDependantJobs($this->graph->getDependantJobs($job['job']))
                ->withDependencies($this->graph->getDependencies($job['job']));
        }

        $workflow->addJobs($this->jobs);

        return [$workflow, $this->graph->getJobsWithoutDependencies()];
    }

    private function serializeCallback($callback): string
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        return serialize($callback);
    }
}
