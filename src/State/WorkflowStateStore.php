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

namespace Sassnowski\Venture\State;

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Venture;

/**
 * @internal
 */
final class WorkflowStateStore
{
    /**
     * @var array<string, FakeWorkflowJobState>
     */
    private static array $workflowJobStates = [];

    /**
     * @var array<int, FakeWorkflowState>
     */
    private static array $workflowStates = [];

    public static function fake(): void
    {
        Venture::useWorkflowJobState(FakeWorkflowJobState::class);
        Venture::useWorkflowState(FakeWorkflowState::class);
    }

    /**
     * @param array<string, FakeWorkflowJobState> $states
     */
    public static function setupJobs(array $states = []): void
    {
        self::fake();

        foreach ($states as $jobID => $state) {
            self::$workflowJobStates[$jobID] = $state;
        }
    }

    public static function setupWorkflow(Workflow $workflow, FakeWorkflowState $state): void
    {
        self::fake();

        self::$workflowStates[$workflow->id] = $state;
    }

    public static function restore(): void
    {
        Venture::useWorkflowJobState(DefaultWorkflowJobState::class);
        Venture::useWorkflowState(DefaultWorkflowState::class);

        self::$workflowJobStates = [];
        self::$workflowStates = [];
    }

    public static function forJob(string $jobID): FakeWorkflowJobState
    {
        if (!isset(self::$workflowJobStates[$jobID])) {
            self::$workflowJobStates[$jobID] = new FakeWorkflowJobState();
        }

        return self::$workflowJobStates[$jobID];
    }

    public static function forWorkflow(Workflow $workflow): FakeWorkflowState
    {
        if (!isset(self::$workflowStates[$workflow->id])) {
            self::$workflowStates[$workflow->id] = new FakeWorkflowState();
        }

        return self::$workflowStates[$workflow->id];
    }
}
