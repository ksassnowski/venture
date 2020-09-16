<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow\Graph;

class DependencyGraph
{
    private array $dependencies = [];
    private array $dependants = [];

    public function addDependantJob($job, array $dependencies): void
    {
        $this->dependencies[get_class($job)] = $dependencies;

        foreach ($dependencies as $dependency) {
            $this->dependants[$dependency][] = $job;
        }
    }

    public function getDependantJobs($job): array
    {
        return $this->dependants[get_class($job)] ?? [];
    }

    public function getDependencies($job): array
    {
        return $this->dependencies[get_class($job)] ?? [];
    }
}
