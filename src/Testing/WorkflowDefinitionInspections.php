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

namespace Sassnowski\Venture\Testing;

use DateInterval;
use DateTimeInterface;
use Sassnowski\Venture\WorkflowDefinition;

/**
 * @internal
 *
 * @mixin WorkflowDefinition
 */
trait WorkflowDefinitionInspections
{
    /**
     * @var array<string, string[]>
     */
    protected array $nestedWorkflows = [];

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
     */
    public function hasJob(string $id, ?array $dependencies = null, mixed $delay = null): bool
    {
        if (null === $dependencies && null === $delay) {
            return $this->jobs->find($id) !== null;
        }

        if (null !== $dependencies && !$this->hasJobWithDependencies($id, $dependencies)) {
            return false;
        }

        if (null !== $delay && !$this->hasJobWithDelay($id, $delay)) {
            return false;
        }

        return true;
    }

    public function hasJobWithDependencies(string $jobId, array $dependencies): bool
    {
        return \count(\array_diff($dependencies, $this->graph->getDependencies($jobId))) === 0;
    }

    /**
     * @param null|DateInterval|DateTimeInterface|int $delay
     */
    public function hasJobWithDelay(string $jobClassName, mixed $delay): bool
    {
        if (null === ($jobDefinition = $this->jobs->find($jobClassName))) {
            return false;
        }

        return $jobDefinition->job->getDelay() == $delay;
    }

    /**
     * @param null|string[] $dependencies
     */
    public function hasWorkflow(string $workflowId, ?array $dependencies = null): bool
    {
        if (!isset($this->nestedWorkflows[$workflowId])) {
            return false;
        }

        if (null === $dependencies) {
            return true;
        }

        return $this->nestedWorkflows[$workflowId] === $dependencies;
    }
}
