<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Closure;
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
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Graph\DependencyInterface;
use Sassnowski\Venture\Graph\StaticDependency;
use Sassnowski\Venture\Models\Workflow;
use Throwable;

class WorkflowDefinition
{
    use Conditionable;

    /**
     * @var array<string, WorkflowStepInterface>
     */
    protected array $jobs = [];

    protected DependencyGraph $graph;

    protected ?string $thenCallback = null;

    protected ?string $catchCallback = null;

    /**
     * @var array<string, array<int, DependencyInterface|string>>
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
     * @param array<int, DependencyInterface|string> $dependencies
     * @param Delay                                  $delay
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
     * @param array<int, DependencyInterface|string> $dependencies
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
     * @param array<int, DependencyInterface|string> $dependencies
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
     * @param callable(Workflow): void $callback
     */
    public function then(callable $callback): self
    {
        $this->thenCallback = $this->serializeCallback($callback);

        return $this;
    }

    /**
     * @param callable(Workflow, WorkflowStepInterface, Throwable): void $callback
     */
    public function catch(callable $callback): self
    {
        $this->catchCallback = $this->serializeCallback($callback);

        return $this;
    }

    /**
     * @param null|Closure(Workflow, array<string, WorkflowStepInterface>): void $beforeCreate
     *
     * @return array{0: Workflow, 1: array<int, WorkflowStepInterface>}
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
     * @return array<string, WorkflowStepInterface>
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

    protected function getJobById(string $className): ?WorkflowStepInterface
    {
        return $this->jobs[$className] ?? null;
    }

    /**
     * @param Delay $delay
     */
    private function onJobAdding(
        WorkflowStepInterface $job,
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
    private function wrapJob(object $step, ?string $id): WorkflowStepInterface
    {
        if ($step instanceof WorkflowStepInterface) {
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
     * @param array<int, DependencyInterface|string> $dependencies
     *
     * @return array<int, DependencyInterface>
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

    private function pushJob(WorkflowStepInterface $job): void
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
}
