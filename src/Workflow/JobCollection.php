<?php

declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Workflow;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Traversable;

/**
 * @implements IteratorAggregate<string, JobDefinition>
 */
final class JobCollection implements Countable, IteratorAggregate
{
    /**
     * @var array<string, JobDefinition>
     */
    private array $jobs = [];

    /**
     * @throws DuplicateJobException
     */
    public function __construct(JobDefinition ...$jobs)
    {
        foreach ($jobs as $jobDefinition) {
            $this->add($jobDefinition);
        }
    }

    /**
     * @throws DuplicateJobException
     */
    public function add(JobDefinition $jobDefinition): void
    {
        if (isset($this->jobs[$jobDefinition->id])) {
            throw new DuplicateJobException("A job with id [{$jobDefinition->id}] already exists");
        }

        $this->jobs[$jobDefinition->id] = $jobDefinition;
    }

    public function find(string $jobId): ?JobDefinition
    {
        return $this->jobs[$jobId] ?? null;
    }

    /**
     * @return object[]
     */
    public function getInstances(): array
    {
        return \array_map(
            fn (JobDefinition $definition) => $definition->job,
            $this->jobs,
        );
    }

    /**
     * @return Traversable<string, JobDefinition>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->jobs);
    }

    public function count(): int
    {
        return \count($this->jobs);
    }
}
