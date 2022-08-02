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

namespace Sassnowski\Venture\Graph;

use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;
use Sassnowski\Venture\WorkflowStepInterface;
use function collect;

class DependencyGraph
{
    /**
     * @var array<string, Node>
     */
    private array $graph = [];

    /**
     * @var array<string, array<string, Node>>
     */
    private array $nestedGraphs = [];

    /**
     * @param array<int, DependencyInterface> $dependencies
     *
     * @throws DuplicateJobException
     */
    public function addDependantJob(WorkflowStepInterface $job, array $dependencies, string $id): void
    {
        if (isset($this->graph[$id])) {
            throw new DuplicateJobException(\sprintf('A job with id "%s" already exists in this workflow.', $id));
        }

        $resolvedDependencies = $this->resolveDependencies($dependencies);

        $node = new Node($id, $job, $resolvedDependencies);

        $this->graph[$id] = $node;

        foreach ($resolvedDependencies as $dependency) {
            $dependency->addDependent($node);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->graph[$id]) || isset($this->nestedGraphs[$id]);
    }

    public function get(string $id): ?Node
    {
        return $this->graph[$id] ?? null;
    }

    /**
     * @return array<int, WorkflowStepInterface>
     */
    public function getDependantJobs(string $jobId): array
    {
        return $this->graph[$jobId]->getDependentJobs();
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(string $jobId): array
    {
        return $this->graph[$jobId]->getDependencyIDs();
    }

    /**
     * @return array<int, WorkflowStepInterface>
     */
    public function getJobsWithoutDependencies(): array
    {
        return collect($this->graph)
            ->filter(fn (Node $node): bool => $node->isRoot())
            ->map(fn (Node $node): WorkflowStepInterface => $node->getJob())
            ->values()
            ->all();
    }

    /**
     * @param array<int, DependencyInterface> $dependencies
     *
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function connectGraph(self $otherGraph, string $id, array $dependencies): void
    {
        if (isset($this->nestedGraphs[$id])) {
            throw new DuplicateWorkflowException(\sprintf('A nested workflow with id "%s" already exists', $id));
        }

        $otherGraph->namespace($id);

        $this->nestedGraphs[$id] = $otherGraph->graph;

        foreach ($otherGraph->graph as $node) {
            // The root nodes of the nested graph should be connected to
            // the provided dependencies. If the dependency happens to be
            // another graph, it will be resolved inside `addDependantJob`.
            if ($node->isRoot()) {
                $nodeDependencies = $dependencies;
            } else {
                $nodeDependencies = \array_map(
                    fn (string $dependency) => new StaticDependency($dependency),
                    $node->getDependencyIDs(),
                );
            }

            $this->addDependantJob($node->getJob(), $nodeDependencies, $node->getID());
        }
    }

    private function namespace(string $prefix): void
    {
        foreach ($this->graph as $node) {
            $node->namespace($prefix);
        }
    }

    /**
     * @param array<int, DependencyInterface> $dependencies
     *
     * @return array<int, Node>
     */
    private function resolveDependencies(array $dependencies): array
    {
        $resolvedDependencies = [];

        foreach ($dependencies as $dependency) {
            $id = $dependency->getID($this);

            if (null === $id) {
                continue;
            }

            $resolvedDependencies[] = $this->resolveDependency($id);
        }

        return \array_merge(...$resolvedDependencies);
    }

    /**
     * @return array<int, Node>
     */
    private function resolveDependency(string $dependency): array
    {
        if (\array_key_exists($dependency, $this->graph)) {
            return [$this->graph[$dependency]];
        }

        // Depending on a nested graph means depending on each of the graph's
        // leaf nodes, i.e. nodes with an out-degree of 0.
        if (\array_key_exists($dependency, $this->nestedGraphs)) {
            return collect($this->nestedGraphs[$dependency])
                ->filter(fn (Node $node) => $node->isLeaf())
                ->values()
                ->all();
        }

        throw new UnresolvableDependenciesException(\sprintf(
            'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
            $dependency,
        ));
    }
}
