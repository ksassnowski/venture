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

namespace Sassnowski\Venture\Persistence\Database;

use Illuminate\Support\Facades\DB;
use Sassnowski\Venture\DTO\Workflow;
use Sassnowski\Venture\Persistence\WorkflowRepository;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use stdClass;

final class DatabaseWorkflowRepository implements WorkflowRepository
{
    public function __construct(private WorkflowFactory $workflowFactory)
    {
    }

    public function find(int|string $workflowId): ?Workflow
    {
        $result = DB::table(config('venture.workflow_table'))->find($workflowId);

        return $this->hydrateRow($result);
    }

    public function markStepAsFinished(WorkflowStepInterface $step): void
    {
    }

    private function hydrateRow(?stdClass $row): ?Workflow
    {
        if (null === $row) {
            return null;
        }

        return $this->workflowFactory->hydrateWorkflow($row);
    }
}
