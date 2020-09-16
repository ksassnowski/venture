<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow\Graph;

class DependencyGraph
{
    private const DEPENDENCY = 1;
    private const DEPENDANT = -1;
    private array $adjacencyMatrix = [];

    public function addDependency(string $src, string $dest)
    {
        $this->adjacencyMatrix[$src][$dest] = self::DEPENDENCY;
        $this->adjacencyMatrix[$dest][$src] = self::DEPENDANT;
    }

    public function getDependants(string $vertex): array
    {
        return array_keys(array_filter($this->adjacencyMatrix[$vertex], function ($value) {
            return $value === self::DEPENDANT;
        }));
    }

    public function getDependencies(string $vertex): array
    {
        return array_keys(array_filter($this->adjacencyMatrix[$vertex], function ($value) {
            return $value === self::DEPENDENCY;
        }));
    }
}
