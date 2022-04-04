<?php

namespace Sassnowski\Venture;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;

class Venture
{
    public static string $workflowModel = Workflow::class;

    public static string $workflowJobModel = WorkflowJob::class;

    public function useWorkflowModel(string $workflowModel): void
    {
        static::$workflowModel = $workflowModel;
    }

    public function useWorkflowJobModel(string $workflowJobModel): void
    {
        static::$workflowJobModel = $workflowJobModel;
    }
}
