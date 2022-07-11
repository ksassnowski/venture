<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use Laravel\SerializableClosure\SerializableClosure;
use Sassnowski\Venture\Events\JobAdded;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\WorkflowAdded;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Graph\DependencyGraph;
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
     * @var array<string, array<int, string>>
     */
    protected array $nestedWorkflows = [];

    public function __construct(
        protected AbstractWorkflow $workflow,
        protected string $workflowName = '',
    ) {
        $this->graph = new DependencyGraph();
    }

    /**
     * @param array<int, string> $dependencies
     * @param Delay              $delay
     *
     * @throws DuplicateJobException
     */
    public function addJob(
        WorkflowStepInterface $job,
        array $dependencies = [],
        ?string $name = null,
        mixed $delay = null,
        ?string $id = null,
    ): self {
        $event = $this->onJobAdding($job, $dependencies, $name, $delay, $id);

        $this->graph->addDependantJob(
            $event->job,
            $event->dependencies,
            $event->job->getJobId(),
        );

        $this->pushJob($event->job);

        event(new JobAdded($this, $event->job, $event->dependencies, $event->job->getName()));

        return $this;
    }

    /**
     * @param array<int, string> $dependencies
     *
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function addWorkflow(AbstractWorkflow $workflow, array $dependencies = [], ?string $id = null): self
    {
        $definition = $workflow->definition();

        $event = $this->onWorkflowAdding($definition, $dependencies, $id);

        $workflow->beforeNesting($definition->jobs);

        /** @psalm-suppress PossiblyNullArgument */
        $this->graph->connectGraph(
            $definition->graph,
            $event->workflowID,
            $event->dependencies,
        );

        foreach ($definition->jobs as $job) {
            $this->pushJob($job);
        }

        $this->nestedWorkflows[$event->workflowID] = $dependencies;

        event(new WorkflowAdded($this, $definition, $event->dependencies, $event->workflowID));

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
    public function hasJob(string $id, ?array $dependencies = null, mixed $delay = null): bool
    {
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
     * @param array<int, string> $dependencies
     * @param Delay              $delay
     */
    private function onJobAdding(
        WorkflowStepInterface $job,
        array $dependencies,
        ?string $name,
        mixed $delay,
        ?string $id,
    ): JobAdding {
        $event = new JobAdding($this, $job, $dependencies, $name, $delay, $id);

        \event($event);

        return $event;
    }

    /**
     * @param array<int, string> $dependencies
     */
    private function onWorkflowAdding(
        self $definition,
        array $dependencies,
        ?string $id,
    ): WorkflowAdding {
        $event = new WorkflowAdding($this, $definition, $dependencies, $id ?: '');

        \event($event);

        return $event;
    }

    private function serializeCallback(mixed $callback): string
    {
        if ($callback instanceof Closure) {
            $callback = new SerializableClosure($callback);
        }

        return \serialize($callback);
    }

    private function pushJob(WorkflowStepInterface $job): void
    {
        $this->jobs[$job->getJobId()] = $job;
    }
}
