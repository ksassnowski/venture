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

use Closure;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowStepInterface;
use Throwable;

final class WorkflowTester
{
    public function __construct(private AbstractWorkflow $workflow)
    {
    }

    /**
     * @param null|Closure(Workflow): void $configureWorkflowCallback
     */
    public function runThenCallback(?Closure $configureWorkflowCallback = null): void
    {
        $this->getWorkflow($configureWorkflowCallback)
            ->runThenCallback();
    }

    /**
     * @param null|Closure(Workflow): void $configureWorkflowCallback
     */
    public function runCatchCallback(
        WorkflowStepInterface $failedJob,
        Throwable $exception,
        ?Closure $configureWorkflowCallback = null,
    ): void {
        $this->getWorkflow($configureWorkflowCallback)
            ->runCatchCallback($failedJob, $exception);
    }

    private function getWorkflow(?Closure $callback = null): Workflow
    {
        $definition = $this->workflow->definition();

        [$workflow, $_] = $definition->build();

        if (null !== $callback) {
            $callback($workflow);
        }

        return $workflow;
    }
}
