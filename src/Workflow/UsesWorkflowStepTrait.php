<?php declare(strict_types=1);

namespace Sassnowski\Venture\Workflow;

use Illuminate\Bus\Queueable;

/**
 * This class only exists so it can be used during static analysis in place of
 * the old trait. It should not be used directly.
 *
 * @internal
 * @psalm-suppress DeprecatedTrait
 */
final class UsesWorkflowStepTrait
{
    use \Sassnowski\Venture\WorkflowStep, Queueable;
}
