<?php declare(strict_types=1);

namespace Sassnowski\Venture\Workflow;

/**
 * @psalm-immutable
 */
final class JobDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public WorkflowStepInterface $job
    ) {
    }
}
