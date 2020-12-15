<?php declare(strict_types=1);

namespace Sassnowski\Venture\Graph;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;
use Stubs\TestJob2;

class DependencyGraph
{
    private array $graph = [];
    private array $nestedGraphs = [];

    private array $classMap = [];

    public function __construct(array $graph = [])
    {
        foreach ($graph as $definition) {
            $definition['instance']->stepId ??= (string) Str::orderedUuid();
            $this->graph[$definition['instance']->stepId] = $definition;
            $this->classMap[get_class($definition['instance'])][] = $definition['instance']->stepId;
        }
    }

    public function addDependantJob($job, array $dependencies): void
    {
        $jobClassName = get_class($job);
        $job->stepId ??= (string) Str::orderedUuid();

        $resolvedDependencies = $this->resolveDependencies($dependencies);
        $this->classMap[$jobClassName][] = $job->stepId;

        $this->graph[$job->stepId]['instance'] = $job;
        $this->graph[$job->stepId]['in_edges'] = $resolvedDependencies;
        $this->graph[$job->stepId]['out_edges'] ??= [];

        foreach ($resolvedDependencies as $dependency) {
            $this->graph[$dependency]['out_edges'][] = $job->stepId;
        }
    }

    public function getDependantJobs($job): array
    {
        $key = $this->resolveStepId($job);

        return collect($this->graph[$key]['out_edges'])
            ->map(function (string $dependantJob) {
                return $this->graph[$dependantJob]['instance'];
            })
            ->toArray();
    }

    public function getDependencies($job): array
    {
        $key = $this->resolveStepId($job);

        return collect($this->graph[$key]['in_edges'])
            ->map(function (string $dependantJob) {
                return get_class($this->graph[$dependantJob]['instance']);
            })
            ->toArray();
    }

    public function getDependenciesAsJobs($job): array
    {
        $key = $this->resolveStepId($job);

        return collect($this->graph[$key]['in_edges'])
            ->map(function (string $dependantJob) {
                return $this->graph[$dependantJob]['instance'];
            })
            ->toArray();
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

    public function resolveDependencies(array $dependencies): array
    {
        return collect($dependencies)->flatMap(function ($dependency) {
            return $this->resolveDependency($dependency);
        })->all();
    }

    private function resolveDependency($dependency): array
    {
        $key = $this->resolveStepId($dependency);
        if (array_key_exists($key, $this->graph)) {
            return [$key];
        }

        // Depending on a nested graph means depending on each of the graph's
        // leaf nodes, i.e. nodes with an out-degree of 0.
        if (array_key_exists($key, $this->nestedGraphs)) {
            return collect($this->nestedGraphs[$key])
                ->filter(fn (array $node) => count($node['out_edges']) === 0)
                ->keys()
                ->all();
        }

        throw new UnresolvableDependenciesException(sprintf(
            'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
            $dependency
        ));
    }

    private function resolveStepId($job): ?string
    {
        // dependency can be a class object, class name or stepId
        $className = is_object($job) ? get_class($job) : $job;
        if (array_key_exists($className, $this->classMap)) {
            return Arr::last($this->classMap[$className] ?? []);
        }

        // we're dealing with a stepId
        return $job;
    }
}
