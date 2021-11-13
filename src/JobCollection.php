<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Countable;
use Traversable;
use ArrayIterator;
use IteratorAggregate;
use Sassnowski\Venture\Exceptions\DuplicateJobException;

final class JobCollection implements IteratorAggregate, Countable
{
    /** @var array<string, JobDefinition> */
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
        return array_map(
            fn (JobDefinition $definition) => $definition->job,
            $this->jobs
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
        return count($this->jobs);
    }
}
