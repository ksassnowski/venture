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

namespace Stubs;

use Closure;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowableJob;
use Sassnowski\Venture\WorkflowDefinition;
use Throwable;

final class WorkflowWithCallbacks extends AbstractWorkflow
{
    /**
     * @param null|Closure(Workflow): void                             $then
     * @param null|Closure(Workflow, WorkflowableJob, Throwable): void $catch
     */
    public function __construct(
        private ?Closure $then = null,
        private ?Closure $catch = null,
    ) {
    }

    public function definition(): WorkflowDefinition
    {
        $definition = $this->define();

        if (null !== $this->then) {
            $definition->then($this->then);
        }

        if (null !== $this->catch) {
            $definition->catch($this->catch);
        }

        return $definition;
    }
}
