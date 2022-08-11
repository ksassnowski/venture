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

use Sassnowski\Venture\WorkflowableJob;

final class Node
{
    private ?string $namespace = null;

    /**
     * @param array<int, Node> $dependencies
     * @param array<int, Node> $dependents
     */
    public function __construct(
        private string $id,
        private WorkflowableJob $instance,
        private array $dependencies,
        private array $dependents = [],
    ) {
    }

    public function getID(): string
    {
        if (null !== $this->namespace) {
            return "{$this->namespace}.{$this->id}";
        }

        return $this->id;
    }

    public function namespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function inNamespace(string $namespace): bool
    {
        return $this->namespace === $namespace;
    }

    public function addDependent(self $node): void
    {
        $this->dependents[] = $node;
    }

    /**
     * @return array<int, string>
     */
    public function getDependencyIDs(): array
    {
        return \array_map(
            fn (Node $node): string => $node->getID(),
            $this->dependencies,
        );
    }

    /**
     * @return array<int, WorkflowableJob>
     */
    public function getDependentJobs(): array
    {
        return \array_map(
            fn (Node $node) => $node->instance,
            $this->dependents,
        );
    }

    public function getJob(): WorkflowableJob
    {
        return $this->instance;
    }

    public function isRoot(): bool
    {
        if (null === $this->namespace) {
            return empty($this->dependencies);
        }

        return collect($this->dependencies)
            ->filter(fn (Node $dependency) => $dependency->inNamespace($this->namespace))
            ->isEmpty();
    }

    public function isLeaf(): bool
    {
        if (null === $this->namespace) {
            return empty($this->dependents);
        }

        return collect($this->dependents)
            ->filter(fn (Node $dependency) => $dependency->inNamespace($this->namespace))
            ->isEmpty();
    }
}
