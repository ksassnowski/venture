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

namespace Sassnowski\Venture\Persistence;

use Sassnowski\Venture\DTO\Workflow;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;

interface WorkflowRepository
{
    public function find(int $workflowId): ?Workflow;

    public function markStepAsFinished(WorkflowStepInterface $step): void;
}
