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
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Collection\JobDefinitionCollection;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Testing\WorkflowDefinitionInspections;
use Sassnowski\Venture\Workflow\JobDefinition;
use Sassnowski\Venture\Workflow\LegacyWorkflowStepAdapter;
use Sassnowski\Venture\Workflow\WorkflowBuilder;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use Throwable;
use const E_USER_DEPRECATED;

class WorkflowDefinition
{
    use WorkflowDefinitionInspections;
    protected JobDefinitionCollection $jobs;
    protected DependencyGraph $graph;
    protected ?string $thenCallback = null;
    protected ?string $catchCallback = null;

    public function __construct(protected string $workflowName = '')
    {
        $this->graph = new DependencyGraph();
        $this->jobs = new JobDefinitionCollection();
    }

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
     *
     * @throws DuplicateJobException
     *
     * @return $this
     *
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function addJob(
        object $job,
        array $dependencies = [],
        ?string $name = null,
        mixed $delay = null,
        ?string $id = null,
    ): self {
        if (!($job instanceof WorkflowStepInterface)) {
            @\trigger_error(
                'Workflow jobs using the "WorkflowStep" trait have been deprecated. Steps should extend from "\Sassnowski\Venture\Workflow\WorkflowStep" instead.',
                E_USER_DEPRECATED,
            );

            $name ??= \get_class($job);

            /** @psalm-suppress ArgumentTypeCoercion */
            $job = LegacyWorkflowStepAdapter::from($job);
        }

        $id = $this->buildIdentifier($id, $job);

        $job->withStepId(Str::orderedUuid())
            ->withDelay($delay);

        $this->graph->addDependantJob($job, $dependencies, $id);

        $jobDefinition = new JobDefinition(
            $id,
            $name ?: \get_class($job),
            $job,
        );

        $this->jobs->add($jobDefinition);

        return $this;
    }

    /**
     * @param string[] $dependencies
     *
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function addWorkflow(WorkflowBuilder $workflow, array $dependencies = [], ?string $id = null): self
    {
        $definition = $workflow->definition();
        $workflowId = $this->buildIdentifier($id, $workflow);

        $workflow->beforeNesting($definition->jobs->getInstances());

        $this->graph->connectGraph($definition->graph, $workflowId, $dependencies);

        foreach ($definition->jobs as $jobId => $jobDefinition) {
            $newId = $workflowId . '.' . $jobId;

            $instance = $jobDefinition->job;

            $this->jobs->add(
                new JobDefinition($newId, $jobDefinition->name, $instance),
            );
        }

        $this->nestedWorkflows[$workflowId] = $dependencies;

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
     * @psalm-param Closure(Workflow): void|null $beforeCreate
     */
    public function build(?Closure $beforeCreate = null): array
    {
        $workflow = new Workflow([
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

    protected function buildIdentifier(?string $id, object $job): string
    {
        if (null !== $id) {
            return $id;
        }

        if ($job instanceof LegacyWorkflowStepAdapter) {
            $job = $job->getWrappedJob();
        }

        return \get_class($job);
    }

    private function serializeCallback(mixed $callback): string
    {
        if ($callback instanceof Closure) {
            $callback = SerializableClosure::from($callback);
        }

        return \serialize($callback);
    }
}
