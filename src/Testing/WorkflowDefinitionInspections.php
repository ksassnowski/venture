<?php declare(strict_types=1);

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
    /** @var array<string, string[]> */
    protected array $nestedWorkflows = [];

    /**
     * @param DateTimeInterface|DateInterval|int|null $delay
     */
    public function hasJob(string $id, ?array $dependencies = null, mixed $delay = null): bool
    {
        if ($dependencies === null && $delay === null) {
            return $this->jobs->find($id) !== null;
        }

        if ($dependencies !== null && !$this->hasJobWithDependencies($id, $dependencies)) {
            return false;
        }

        if ($delay !== null && !$this->hasJobWithDelay($id, $delay)) {
            return false;
        }

        return true;
    }

    public function hasJobWithDependencies(string $jobId, array $dependencies): bool
    {
        return count(array_diff($dependencies, $this->graph->getDependencies($jobId))) === 0;
    }

    /**
     * @param DateTimeInterface|DateInterval|int|null $delay
     */
    public function hasJobWithDelay(string $jobClassName, mixed $delay): bool
    {
        if (($jobDefinition = $this->jobs->find($jobClassName)) === null) {
            return false;
        }

        return $jobDefinition->job->getDelay() == $delay;
    }

    /**
     * @param string[]|null $dependencies
     */
    public function hasWorkflow(string $workflowId, ?array $dependencies = null): bool
    {
        if (!isset($this->nestedWorkflows[$workflowId])) {
            return false;
        }

        if ($dependencies === null) {
            return true;
        }

        return $this->nestedWorkflows[$workflowId] === $dependencies;
    }
}
