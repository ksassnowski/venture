<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Closure;
use DateInterval;
use function count;
use DateTimeInterface;
use function array_diff;
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Models\Workflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Exceptions\NonQueueableWorkflowStepException;

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
     *
     * @throws NonQueueableWorkflowStepException
     */
    public function addJob($job, array $dependencies = [], ?string $name = null, $delay = null): self
    {
        if (!($job instanceof ShouldQueue)) {
            throw new NonQueueableWorkflowStepException();
        }

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

    /**
     * @param object                                  $job
     * @param DateTimeInterface|DateInterval|int|null $delay
     * @param array                                   $dependencies
     * @param string|null                             $name
     *
     * @return $this
     */
    public function addJobWithDelay(object $job, $delay, array $dependencies = [], ?string $name = null): self
    {
        return $this->addJob($job, $dependencies, $name, $delay);
    }

    public function addWorkflow(AbstractWorkflow $workflow, array $dependencies = []): self
    {
        $definition = $workflow->definition();

        $this->graph->connectGraph($definition->graph, get_class($workflow), $dependencies);

        foreach ($definition->jobs as $job) {
            $this->jobs[] = $job;
        }

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

    public function build(?Closure $beforeCreate = null): array
    {
        $workflow = new Workflow([
            'name' => $this->workflowName,
            'job_count' => count($this->jobs),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
            'then_callback' => $this->thenCallback,
            'catch_callback' => $this->catchCallback,
        ]);

        if ($beforeCreate !== null) {
            $beforeCreate($workflow);
        }

        $workflow->save();

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

    public function name(): string
    {
        return $this->workflowName;
    }

    private function serializeCallback($callback): string
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        return serialize($callback);
    }

    public function hasJob(string $jobClassName, ?array $dependencies = null, $delay = null): bool
    {
        if ($dependencies === null && $delay === null) {
            return $this->getJobByClassName($jobClassName) !== null;
        }

        if ($dependencies !== null && !$this->hasJobWithDependencies($jobClassName, $dependencies)) {
            return false;
        }

        if ($delay !== null && !$this->hasJobWithDelay($jobClassName, $delay)) {
            return false;
        }

        return true;
    }

    public function hasJobWithDependencies(string $jobClassName, array $dependencies): bool
    {
        return count(array_diff($dependencies, $this->graph->getDependencies($jobClassName))) === 0;
    }

    public function hasJobWithDelay(string $jobClassName, $delay): bool
    {
        if (($job = $this->getJobByClassName($jobClassName)) === null) {
            return false;
        }

        return $job['job']->delay == $delay;
    }

    private function getJobByClassName(string $className): ?array
    {
        return collect($this->jobs)->first(function (array $job) use ($className) {
            return get_class($job['job']) === $className;
        });
    }
}
