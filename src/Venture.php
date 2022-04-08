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

namespace Sassnowski\Venture;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

class Venture
{
    public static string $workflowModel = Workflow::class;
    public static string $workflowJobModel = WorkflowJob::class;

    public static function useWorkflowModel(string $workflowModel): void
    {
        static::$workflowModel = $workflowModel;
    }

    public static function useWorkflowJobModel(string $workflowJobModel): void
    {
        static::$workflowJobModel = $workflowJobModel;
    }
}
