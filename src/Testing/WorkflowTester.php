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
use RuntimeException;
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
        $this->runCallback('then', $configureWorkflowCallback);
    }

    /**
     * @param null|Closure(Workflow): void $configureWorkflowCallback
     */
    public function runCatchCallback(
        WorkflowStepInterface $failedJob,
        Throwable $exception,
        ?Closure $configureWorkflowCallback = null,
    ): void {
        $this->runCallback('catch', $configureWorkflowCallback, $failedJob, $exception);
    }

    /**
     * @param null|Closure(Workflow): void $configureWorkflowCallback
     */
    private function runCallback(
        string $callback,
        ?Closure $configureWorkflowCallback,
        mixed ...$arguments,
    ): void {
        $definition = $this->workflow->definition();

        [$workflow, $_] = $definition->build();

        $callbackName = "{$callback}_callback";
        $serializedCallback = $workflow->{$callbackName};

        if (null === $serializedCallback) {
            throw new RuntimeException(\sprintf(
                'No %s-callback configured for workflow %s',
                $callback,
                $this->workflow::class,
            ));
        }

        if (null !== $configureWorkflowCallback) {
            $configureWorkflowCallback($workflow);
        }

        /** @var callable $callback */
        $callback = \unserialize($serializedCallback);

        $callback($workflow, ...$arguments);
    }
}
