<?php declare(strict_types=1);

namespace Sassnowski\Venture\Graph;

class DependencyGraph
{
    private array $unresolvableDependencies = [];
    private array $graph = [];

    public function addDependantJob($job, array $dependencies): void
    {
        $jobClassName = get_class($job);

        $this->graph[$jobClassName]['instance'] = $job;
        $this->graph[$jobClassName]['in_edges'] = $dependencies;
        $this->graph[$jobClassName]['out_edges'] ??= [];

        foreach ($dependencies as $dependency) {
            $this->graph[$dependency]['out_edges'][] = $jobClassName;

            if (!isset($this->graph[$dependency]['instance'])) {
                $this->unresolvableDependencies[$dependency][] = $jobClassName;
            }
        }

        if (array_key_exists($jobClassName, $this->unresolvableDependencies)) {
            unset($this->unresolvableDependencies[$jobClassName]);
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

    public function getUnresolvableDependencies(): array
    {
        return $this->unresolvableDependencies;
    }
}
