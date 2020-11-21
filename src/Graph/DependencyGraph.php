<?php declare(strict_types=1);

namespace Sassnowski\Venture\Graph;

use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;

class DependencyGraph
{
    private array $graph;
    private array $nestedGraphs = [];

    public function __construct(array $graph = [])
    {
        $this->graph = $graph;
    }

    public function addDependantJob($job, array $dependencies): void
    {
        $jobClassName = get_class($job);
        $resolvedDependencies = $this->resolveDependencies($dependencies);

        $this->graph[$jobClassName]['instance'] = $job;
        $this->graph[$jobClassName]['in_edges'] = $resolvedDependencies;
        $this->graph[$jobClassName]['out_edges'] ??= [];

        foreach ($resolvedDependencies as $dependency) {
            $this->graph[$dependency]['out_edges'][] = $jobClassName;
        }
    }

    public function getDependantJobs($job): array
    {
        $key = is_object($job) ? get_class($job) : $job;

        return collect($this->graph[$key]['out_edges'])
            ->map(function (string $dependantJob) {
                return $this->graph[$dependantJob]['instance'];
            })
            ->toArray();
    }

    public function getDependencies($job): array
    {
        $key = is_object($job) ? get_class($job) : $job;

        return $this->graph[$key]['in_edges'];
    }

    public function getJobsWithoutDependencies(): array
    {
        return collect($this->graph)
            ->filter(fn (array $node) => count($node['in_edges']) === 0)
            ->map(fn (array $node) => $node['instance'])
            ->values()
            ->toArray();
    }

    public function connectGraph(DependencyGraph $otherGraph, string $id, array $dependencies): void
    {
        $this->nestedGraphs[$id] = $otherGraph->graph;

        foreach ($otherGraph->graph as $node) {
            // The root nodes of the nested graph should be connected to
            // the provided dependencies. If the dependency happens to be
            // another graph, it will be resolved inside `addDependantJob`.
            if (count($node['in_edges']) === 0) {
                $node['in_edges'] = $dependencies;
            }

            $this->addDependantJob($node['instance'], $node['in_edges']);
        }
    }

    private function resolveDependencies(array $dependencies): array
    {
        return collect($dependencies)->flatMap(function (string $dependency) {
            return $this->resolveDependency($dependency);
        })->all();
    }

    private function resolveDependency(string $dependency): array
    {
        if (array_key_exists($dependency, $this->graph)) {
            return [$dependency];
        }

        // Depending on a nested graph means depending on each of the graph's
        // leaf nodes, i.e. nodes with an out-degree of 0.
        if (array_key_exists($dependency, $this->nestedGraphs)) {
            return collect($this->nestedGraphs[$dependency])
                ->filter(fn (array $node) => count($node['out_edges']) === 0)
                ->keys()
                ->all();
        }

        throw new UnresolvableDependenciesException(sprintf(
            'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
            $dependency
        ));
    }
}
