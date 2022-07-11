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

namespace Sassnowski\Venture\Events;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowStepInterface;

final class WorkflowStarted
{
    /**
     * @param array<int, WorkflowStepInterface> $initialJobs
     */
    public function __construct(
        public AbstractWorkflow $workflow,
        public Workflow $model,
        public array $initialJobs,
    ) {
    }
}
