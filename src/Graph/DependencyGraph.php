<?php declare(strict_types=1);

namespace Sassnowski\Venture\Graph;

use Illuminate\Support\Collection;

class DependencyGraph
{
    private array $dependencies = [];
    private array $dependants = [];
    private array $instances = [];

    public function addDependantJob($job, array $dependencies): void
    {
        $this->dependencies[get_class($job)] = $dependencies;

        foreach ($dependencies as $dependency) {
            $this->dependants[$dependency][] = $job;
        }

        $this->instances[get_class($job)] = $job;
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
}
