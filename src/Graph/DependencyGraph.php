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

use Illuminate\Support\Collection;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;
use Sassnowski\Venture\WorkflowStepInterface;
use function collect;

/**
 * @psalm-type Graph array<string, array{instance: WorkflowStepInterface, in_edges: array<int, string>, out_edges: array<int, string>}>
 */
class DependencyGraph
{
    /**
     * @var array<string, Graph>
     */
    private array $nestedGraphs = [];

    /**
     * @param Graph $graph
     */
    public function __construct(protected array $graph = [])
    {
    }

    /**
     * @param array<int, string> $dependencies
     *
     * @throws DuplicateJobException
     */
    public function addDependantJob(WorkflowStepInterface $job, array $dependencies, string $id): void
    {
        if (isset($this->graph[$id])) {
            throw new DuplicateJobException(\sprintf('A job with id "%s" already exists in this workflow.', $id));
        }

        $resolvedDependencies = $this->resolveDependencies($dependencies);

        $this->graph[$id]['instance'] = $job;
        $this->graph[$id]['in_edges'] = $resolvedDependencies;
        $this->graph[$id]['out_edges'] ??= [];

        foreach ($resolvedDependencies as $dependency) {
            $this->graph[$dependency]['out_edges'][] = $id;
        }
    }

    /**
     * @return array<int, WorkflowStepInterface>
     */
    public function getDependantJobs(string $jobId): array
    {
        return (new Collection($this->graph[$jobId]['out_edges']))
            ->map(fn (string $dependantJob): WorkflowStepInterface => $this->graph[$dependantJob]['instance'])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getDependencies(string $jobId): array
    {
        return $this->graph[$jobId]['in_edges'];
    }

    /**
     * @return array<int, WorkflowStepInterface>
     */
    public function getJobsWithoutDependencies(): array
    {
        return (new Collection($this->graph))
            ->filter(fn (array $node): bool => \count($node['in_edges']) === 0)
            ->map(fn (array $node): WorkflowStepInterface => $node['instance'])
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $dependencies
     *
     * @throws DuplicateJobException
     * @throws DuplicateWorkflowException
     */
    public function connectGraph(self $otherGraph, string $id, array $dependencies): void
    {
        if (isset($this->nestedGraphs[$id])) {
            throw new DuplicateWorkflowException(\sprintf('A nested workflow with id "%s" already exists', $id));
        }

        $this->nestedGraphs[$id] = $otherGraph->graph;

        foreach ($otherGraph->graph as $nodeId => $node) {
            $isAlreadyPrefixed = \str_starts_with($nodeId, $id);

            if (\count($node['in_edges']) === 0) {
                // The root nodes of the nested graph should be connected to
                // the provided dependencies. If the dependency happens to be
                // another graph, it will be resolved inside `addDependantJob`.
                $node['in_edges'] = $dependencies;
            } else {
                if (!$isAlreadyPrefixed) {
                    // All dependencies inside the nested graph get namespaced
                    // to avoid any ambiguity with the jobs from the outer workflow.
                    $node['in_edges'] = collect($node['in_edges'])
                        ->map(fn (string $edgeId): string => $id . '.' . $edgeId)
                        ->all();
                }
            }

            if (!$isAlreadyPrefixed) {
                $nodeId = $id . '.' . $nodeId;
            }

            $this->addDependantJob($node['instance'], $node['in_edges'], $nodeId);
        }
    }

    /**
     * @param array<int, string> $dependencies
     *
     * @return array<int, string>
     */
    private function resolveDependencies(array $dependencies): array
    {
        return (new Collection($dependencies))
            ->flatMap(
                fn (string $dependency) => $this->resolveDependency($dependency),
            )
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveDependency(string $dependency): array
    {
        if (\array_key_exists($dependency, $this->graph)) {
            return [$dependency];
        }

        // Depending on a nested graph means depending on each of the graph's
        // leaf nodes, i.e. nodes with an out-degree of 0.
        if (\array_key_exists($dependency, $this->nestedGraphs)) {
            return (new Collection($this->nestedGraphs[$dependency]))
                ->filter(fn (array $node) => \count($node['out_edges']) === 0)
                ->keys()
                ->map(fn (string $key) => $dependency . '.' . $key)
                ->all();
        }

        throw new UnresolvableDependenciesException(\sprintf(
            'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
            $dependency,
        ));
    }
}
