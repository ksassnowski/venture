<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Laravel\SerializableClosure\SerializableClosure;
use Sassnowski\Venture\Events\JobAdded;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\WorkflowAdded;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\InvalidJobException;
use Sassnowski\Venture\Graph\Dependency;
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Graph\StaticDependency;
use Sassnowski\Venture\Models\Workflow;
use Throwable;

class WorkflowDefinition
{
    use Conditionable;

    /**
     * @var array<string, WorkflowableJob>
     */
    protected array $jobs = [];

    protected DependencyGraph $graph;

    protected ?string $thenCallback = null;

    protected ?string $catchCallback = null;

    /**
     * @var array<string, array<int, Dependency|string>>
     */
    protected array $nestedWorkflows = [];

    protected ?string $connection = null;

    protected ?string $queue = null;

    public function __construct(
        protected AbstractWorkflow $workflow,
        protected string $workflowName = '',
    ) {
        $this->graph = new DependencyGraph();
    }

    public function allOnConnection(string $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function allOnQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * @param array<int, Dependency|string> $dependencies
     * @param Delay                         $delay
     *
     * @throws DuplicateJobException
     * @throws InvalidJobException
     */
    public function addJob(
        object $job,
        array $dependencies = [],
        ?string $name = null,
        mixed $delay = null,
        ?string $id = null,
    ): self {
        $job = $this->wrapJob($job, $id);

        $event = $this->onJobAdding($job, $name, $delay, $id);

        $this->graph->addDependantJob(
            $event->job,
            $this->mapDependencies($dependencies),
            $event->job->getJobId(),
        );

        $this->pushJob($event->job);

        event(new JobAdded($this, $event->job));

        return $this;
    }

    /**
     * @param array<int, Dependency|string> $dependencies
     *
     * @throws DuplicateJobException
     * @throws InvalidJobException
     */
    public function addGatedJob(
        object $job,
        array $dependencies = [],
        ?string $name = null,
        ?string $id = null,
    ): self {
        return $this->addJob(
            $this->wrapJob($job, $id)->withGate(),
            $this->mapDependencies($dependencies),
            $name,
            null,
            $id,
        );
    }

    /**
     * @param array<int, Dependency|string> $dependencies
     *
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function addWorkflow(AbstractWorkflow $workflow, array $dependencies = [], ?string $id = null): self
    {
        $definition = $workflow->getDefinition();

        $event = $this->onWorkflowAdding($definition, $id);

        $workflow->beforeNesting($definition->jobs);

        $this->graph->connectGraph(
            $definition->graph,
            $event->workflowID,
            $this->mapDependencies($dependencies),
        );

        foreach ($definition->jobs as $job) {
            $this->pushJob($job);
        }

        $this->nestedWorkflows[$event->workflowID] = $dependencies;

        event(new WorkflowAdded($this, $definition, $event->workflowID));

        return $this;
    }

    /**
     * Runs the provided callback on each element inside `$collection` and adds the
     * resulting job or workflow to the workflow. This method will enumerate the
     * id of each job or workflow by adding `_$i` to the end of it.
     *
     * If an explicit `$id` is provided, the new jobs or workflows will be registered
     * as a group in the dependency graph. This group can then be depended on by
     * another job or workflow by using `GroupDependency::forGroup($groupName)`, where
     * `$groupName` is the `$id` that passed to this method.
     *
     * @param array<array-key, mixed>|Collection<array-key, mixed> $collection
     * @param Closure(mixed): WorkflowableJob                      $factory
     * @param Delay                                                $delay
     * @param array<int, string>                                   $dependencies
     */
    public function each(
        array|Collection $collection,
        Closure $factory,
        array $dependencies = [],
        ?string $name = null,
        mixed $delay = null,
        ?string $id = null,
    ): self {
        /** @var array<int, Dependency> $jobIds */
        $jobIds = [];

        foreach ($collection as $i => $item) {
            $job = $factory($item);

            // We want to make sure we're resolving the id via the registered `StepIdGenerator`
            // if no explicit id was provided. Otherwise, this would potentially behave
            // differently than adding each job manually would.
            $jobId = ($id ?: $this->resolveJobId($job)).'_'.$i+1;

            if ($job instanceof AbstractWorkflow) {
                $this->addWorkflow($job, $dependencies, $jobId);
                $jobIds[] = new StaticDependency($jobId);
            } else {
                $this->addJob($job, $dependencies, $name, $delay, $jobId);

                // We have to make sure to grab the id from the job itself here instead of using
                // the id we came up with ourselves. This is because an event listener could
                // potentially have modified it, and we would be discarding this change, otherwise.
                $jobIds[] = new StaticDependency($job->getJobId());
            }
        }

        // We only want to define a group if an explicit id was provided by the user. This is
        // because when no id is provided, we really have no way of knowing what to call the
        // group since there is no guarantee that the provided factory function only returns
        // instances of the same class.
        if ($id !== null && \count($jobIds) > 0) {
            $this->graph->defineGroup($id, $jobIds);
        }

        return $this;
    }

    /**
     * @param callable(Workflow): void $callback
     */
    public function then(callable $callback): self
    {
        $this->thenCallback = $this->serializeCallback($callback);

        return $this;
    }

    /**
     * @param callable(Workflow, WorkflowableJob, Throwable): void $callback
     */
    public function catch(callable $callback): self
    {
        $this->catchCallback = $this->serializeCallback($callback);

        return $this;
    }

    /**
     * @param null|Closure(Workflow, array<string, WorkflowableJob>): void $beforeCreate
     *
     * @return array{0: Workflow, 1: array<int, WorkflowableJob>}
     */
    public function build(?Closure $beforeCreate = null): array
    {
        $this->setQueueParametersOnJobs();

        $workflow = $this->makeWorkflow([
            'name' => $this->workflowName,
            'job_count' => \count($this->jobs),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
            'then_callback' => $this->thenCallback,
            'catch_callback' => $this->catchCallback,
        ]);

        event(new WorkflowCreating($this, $workflow));

        if (null !== $beforeCreate) {
            $beforeCreate($workflow, $this->jobs);
        }

        $workflow->save();

        event(new WorkflowCreated($this, $workflow));

        $workflow->addJobs($this->jobs);

        return [$workflow, $this->graph->getJobsWithoutDependencies()];
    }

    public function name(): string
    {
        return $this->workflowName;
    }

    /**
     * @return array<string, WorkflowableJob>
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    public function graph(): DependencyGraph
    {
        return $this->graph;
    }

    public function workflow(): AbstractWorkflow
    {
        return $this->workflow;
    }

    /**
     * @param Delay                   $delay
     * @param null|array<int, string> $dependencies
     */
    public function hasJob(
        string $id,
        ?array $dependencies = null,
        mixed $delay = null,
        bool $gated = false,
    ): bool {
        $job = $this->getJobById($id);

        if (null === $job) {
            return false;
        }

        if ($job->isGated() !== $gated) {
            return false;
        }

        if (null === $dependencies && null === $delay) {
            return $this->getJobById($id) !== null;
        }

        if (null !== $dependencies && !$this->hasJobWithDependencies($id, $dependencies)) {
            return false;
        }

        if (null !== $delay && !$this->hasJobWithDelay($id, $delay)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $dependencies
     */
    public function hasJobWithDependencies(string $jobId, array $dependencies): bool
    {
        return \count(\array_diff($dependencies, $this->graph->getDependencies($jobId))) === 0;
    }

    /**
     * @param Delay $delay
     */
    public function hasJobWithDelay(string $jobClassName, mixed $delay): bool
    {
        if (null === ($job = $this->getJobById($jobClassName))) {
            return false;
        }

        return $job->getDelay() == $delay;
    }

    /**
     * @param null|array<int, string> $dependencies
     */
    public function hasWorkflow(string $workflowId, ?array $dependencies = null): bool
    {
        if (!isset($this->nestedWorkflows[$workflowId])) {
            return false;
        }

        if (null === $dependencies) {
            return true;
        }

        return $this->nestedWorkflows[$workflowId] === $dependencies;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function makeWorkflow(array $attributes): Workflow
    {
        return app(Venture::$workflowModel, \compact('attributes'));
    }

    protected function getJobById(string $className): ?WorkflowableJob
    {
        return $this->jobs[$className] ?? null;
    }

    /**
     * @param Delay $delay
     */
    private function onJobAdding(
        WorkflowableJob $job,
        ?string $name,
        mixed $delay,
        ?string $id,
    ): JobAdding {
        return tap(
            new JobAdding($this, $job, $name, $delay, $id),
            fn (JobAdding $event) => \event($event),
        );
    }

    private function onWorkflowAdding(
        self $definition,
        ?string $id,
    ): WorkflowAdding {
        return tap(
            new WorkflowAdding($this, $definition, $id ?: ''),
            fn (WorkflowAdding $event) => \event($event),
        );
    }

    private function serializeCallback(mixed $callback): string
    {
        if ($callback instanceof Closure) {
            $callback = new SerializableClosure($callback);
        }

        return \serialize($callback);
    }

    /**
     * @throw InvalidJobException
     */
    private function wrapJob(object $step, ?string $id): WorkflowableJob
    {
        if ($step instanceof WorkflowableJob) {
            return $step;
        }

        if ($step instanceof Closure) {
            if (null === $id) {
                throw InvalidJobException::closureWithoutID();
            }

            return new ClosureWorkflowStep($step);
        }

        try {
            $job = WorkflowStepAdapter::fromJob($step);

            @\trigger_error(
                "Jobs that don't implement WorkflowStepInterface are deprecated and support for them will be dropped in Venture 5",
                \E_USER_DEPRECATED,
            );

            return $job;
        } catch (InvalidArgumentException $e) {
            throw InvalidJobException::jobNotUsingTrait($step, $e);
        }
    }

    /**
     * @param array<int, Dependency|string> $dependencies
     *
     * @return array<int, Dependency>
     */
    private function mapDependencies(array $dependencies): array
    {
        $result = [];

        foreach ($dependencies as $dependency) {
            if (\is_string($dependency)) {
                $dependency = new StaticDependency($dependency);
            }

            $result[] = $dependency;
        }

        return $result;
    }

    private function pushJob(WorkflowableJob $job): void
    {
        $this->jobs[$job->getJobId()] = $job;
    }

    private function setQueueParametersOnJobs(): void
    {
        if (null === $this->connection && null === $this->queue) {
            return;
        }

        foreach ($this->jobs as $job) {
            $job->onQueue($this->queue ?: $job->getQueue());
            $job->onConnection($this->connection ?: $job->getConnection());
        }
    }

    private function resolveJobId(WorkflowableJob $job): string
    {
        /** @var StepIdGenerator $stepIdGenerator */
        $stepIdGenerator = Container::getInstance()->make(StepIdGenerator::class);

        return $stepIdGenerator->generateId($job);
    }
}
