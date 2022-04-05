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
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\NonQueueableWorkflowStepException;
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Models\Workflow;

class WorkflowDefinition
{
    protected array $jobs = [];
    protected DependencyGraph $graph;
    protected ?string $thenCallback = null;
    protected ?string $catchCallback = null;
    protected array $nestedWorkflows = [];
    private StepIdGenerator $stepIdGenerator;

    public function __construct(
        protected string $workflowName = '',
        ?StepIdGenerator $stepIdGenerator = null,
    ) {
        $this->graph = new DependencyGraph();
        $this->stepIdGenerator = $stepIdGenerator ?: new ClassNameStepIdGenerator();
    }

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
     *
     * @throws DuplicateJobException
     * @throws NonQueueableWorkflowStepException
     *
     * @return $this
     *
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function addJob(
        object $job,
        array $dependencies = [],
        ?string $name = null,
        $delay = null,
        ?string $id = null,
    ): self {
        if (!($job instanceof ShouldQueue)) {
            throw NonQueueableWorkflowStepException::fromJob($job);
        }

        $id ??= $this->stepIdGenerator->generateId($job);

        $this->graph->addDependantJob($job, $dependencies, $id);

        if (null !== $delay) {
            $job->delay($delay);
        }

        $this->jobs[$id] = [
            'job' => $job
                ->withJobId($id)
                ->withStepId(Str::orderedUuid()),
            'name' => $name ?: \get_class($job),
        ];

        return $this;
    }

    /**
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function addWorkflow(AbstractWorkflow $workflow, array $dependencies = [], ?string $id = null): self
    {
        $definition = $workflow->definition();
        $workflowId = $this->buildIdentifier($id, $workflow);

        $workflow->beforeNesting($definition->getJobInstances());

        $this->graph->connectGraph($definition->graph, $workflowId, $dependencies);

        foreach ($definition->jobs as $jobId => $job) {
            $job['job'] = $job['job']->withJobId($workflowId . '.' . $jobId);
            $this->jobs[$workflowId . '.' . $jobId] = $job;
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
        $workflow = $this->makeWorkflow([
            'name' => $this->workflowName,
            'job_count' => \count($this->jobs),
            'jobs_processed' => 0,
            'jobs_failed' => 0,
            'finished_jobs' => [],
            'then_callback' => $this->thenCallback,
            'catch_callback' => $this->catchCallback,
        ]);

        if (null !== $beforeCreate) {
            $beforeCreate($workflow);
        }

        $workflow->save();

        foreach ($this->jobs as $id => $job) {
            $job['job']
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

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
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

    public function hasJobWithDependencies(string $jobId, array $dependencies): bool
    {
        return \count(\array_diff($dependencies, $this->graph->getDependencies($jobId))) === 0;
    }

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
     */
    public function hasJobWithDelay(string $jobClassName, mixed $delay): bool
    {
        if (null === ($job = $this->getJobById($jobClassName))) {
            return false;
        }

        return $job['job']->delay == $delay;
    }

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

    protected function makeWorkflow(array $attributes): Workflow
    {
        return app(Venture::$workflowModel, \compact('attributes'));
    }

    protected function buildIdentifier(?string $id, object $object): string
    {
        return $id ?: \get_class($object);
    }

    protected function getJobById(string $className): ?array
    {
        return $this->jobs[$className] ?? null;
    }

    protected function getJobInstances(): array
    {
        return collect($this->jobs)
            ->map(fn (array $job): object => $job['job'])
            ->all();
    }

    private function serializeCallback(mixed $callback): string
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        return \serialize($callback);
    }
}
