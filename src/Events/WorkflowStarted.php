<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Sassnowski\Venture\Events;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowableJob;

final class WorkflowStarted
{
    /**
     * @param array<int, WorkflowableJob> $initialJobs
     */
    public function __construct(
        public AbstractWorkflow $workflow,
        public Workflow $model,
        public array $initialJobs,
    ) {
    }
}
