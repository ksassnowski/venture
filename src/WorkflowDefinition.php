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
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\NonQueueableWorkflowStepException;

class WorkflowDefinition
{
    protected JobCollection $jobs;
    protected DependencyGraph $graph;
    protected ?string $thenCallback = null;
    protected ?string $catchCallback = null;
    /** @var array<string, string[]> */
    protected array $nestedWorkflows = [];

    public function __construct(protected string $workflowName = '')
    {
        $this->graph = new DependencyGraph();
        $this->jobs = new JobCollection();
    }

    /**
     * @param  object                                  $job
     * @param  array                                   $dependencies
     * @param  string|null                             $name
     * @param  DateTimeInterface|DateInterval|int|null $delay
     * @param  string|null                             $id
     * @return $this
     *
     * @psalm-suppress UndefinedInterfaceMethod
     *
     * @throws NonQueueableWorkflowStepException
     * @throws DuplicateJobException
     */
    public function addJob(
        object $job,
        array $dependencies = [],
        ?string $name = null,
        mixed $delay = null,
        ?string $id = null
    ): self {
        if (!($job instanceof ShouldQueue)) {
            throw NonQueueableWorkflowStepException::fromJob($job);
        }

        $id = $this->buildIdentifier($id, $job);

        $this->graph->addDependantJob($job, $dependencies, $id);

        if ($delay !== null) {
            $job->delay($delay);
        }

        $jobDefinition = new JobDefinition(
            $id,
            $name ?: get_class($job),
            $job->withJobId($id)->withStepId(Str::orderedUuid()),
        );

        $this->jobs->add($jobDefinition);

        return $this;
    }

    /**
     * @param string[] $dependencies
     *
     * @throws DuplicateWorkflowException
     * @throws DuplicateJobException
     */
    public function addWorkflow(AbstractWorkflow $workflow, array $dependencies = [], ?string $id = null): self
    {
        $definition = $workflow->definition();
        $workflowId = $this->buildIdentifier($id, $workflow);

        $workflow->beforeNesting($definition->jobs->getInstances());

        $this->graph->connectGraph($definition->graph, $workflowId, $dependencies);

        foreach ($definition->jobs as $jobId => $jobDefinition) {
            $newId = $workflowId . '.' . $jobId;

            $instance = $jobDefinition->job;
            $instance->withJobId($newId);

            $this->jobs->add(
                new JobDefinition($newId, $jobDefinition->name, $instance)
            );
        }

        $this->nestedWorkflows[$workflowId] = $dependencies;

        return $this;
    }

    public function then(callable $callback): self
    {
        $this->thenCallback = $this->serializeCallback($callback);

        return $this;
    }

    public function catch(callable $callback): self
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

        foreach ($this->jobs as $id => $jobDefinition) {
            $jobDefinition->job
                ->withWorkflowId($workflow->id)
                ->withDependantJobs($this->graph->getDependantJobs($id))
                ->withDependencies($this->graph->getDependencies($id));
        }

        $workflow->addJobs($this->jobs);

        return [$workflow, $this->graph->getJobsWithoutDependencies()];
    }

    public function name(): string
    {
        return $this->workflowName;
    }

    private function serializeCallback(mixed $callback): string
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        return serialize($callback);
    }

    /**
     * @param DateTimeInterface|DateInterval|int|null $delay
     */
    public function hasJob(string $id, ?array $dependencies = null, mixed $delay = null): bool
    {
        if ($dependencies === null && $delay === null) {
            return $this->jobs->find($id) !== null;
        }

        if ($dependencies !== null && !$this->hasJobWithDependencies($id, $dependencies)) {
            return false;
        }

        if ($delay !== null && !$this->hasJobWithDelay($id, $delay)) {
            return false;
        }

        return true;
    }

    public function hasJobWithDependencies(string $jobId, array $dependencies): bool
    {
        return count(array_diff($dependencies, $this->graph->getDependencies($jobId))) === 0;
    }

    /**
     * @param DateTimeInterface|DateInterval|int|null $delay
     */
    public function hasJobWithDelay(string $jobClassName, mixed $delay): bool
    {
        if (($jobDefinition = $this->jobs->find($jobClassName)) === null) {
            return false;
        }

        return $jobDefinition->job->delay == $delay;
    }

    /**
     * @param string[]|null $dependencies
     */
    public function hasWorkflow(string $workflowId, ?array $dependencies = null): bool
    {
        if (!isset($this->nestedWorkflows[$workflowId])) {
            return false;
        }

        if ($dependencies === null) {
            return true;
        }

        return $this->nestedWorkflows[$workflowId] === $dependencies;
    }

    protected function buildIdentifier(?string $id, object $object): string
    {
        return $id ?: get_class($object);
    }
}
