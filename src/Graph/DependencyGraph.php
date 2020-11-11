<?php declare(strict_types=1);

namespace Sassnowski\Venture\Graph;

use Illuminate\Support\Collection;

class DependencyGraph
{
    private array $unresolvableDependencies = [];
    private array $dependencies = [];
    private array $dependants = [];
    private array $instances = [];

    public function addDependantJob($job, array $dependencies): void
    {
        $jobClassName = get_class($job);

        $this->dependencies[$jobClassName] = $dependencies;

        foreach ($dependencies as $dependency) {
            if (!array_key_exists($dependency, $this->instances)) {
                $this->unresolvableDependencies[$dependency][] = $jobClassName;
            }

            $this->dependants[$dependency][] = $job;
        }

        if (array_key_exists($jobClassName, $this->unresolvableDependencies)) {
            unset($this->unresolvableDependencies[$jobClassName]);
        }

        $this->instances[$jobClassName] = $job;
    }

    public function getDependantJobs($job): array
    {
        $key = is_object($job) ? get_class($job) : $job;

        return $this->dependants[$key] ?? [];
    }

    public function getDependencies($job): array
    {
        $key = is_object($job) ? get_class($job) : $job;

        return $this->dependencies[$key] ?? [];
    }

    public function getJobsWithoutDependencies(): array
    {
        return collect($this->dependencies)
            ->filter(fn (array $deps) => count($deps) === 0)
            ->keys()
            ->pipe(function (Collection $jobNames) {
                return collect($this->instances)
                    ->only($jobNames)
                    ->values()
                    ->toArray();
            });
    }

    public function getUnresolvableDependencies(): array
    {
        return $this->unresolvableDependencies;
    }
}
