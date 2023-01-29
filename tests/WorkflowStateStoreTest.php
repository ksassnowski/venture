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

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\State\DefaultWorkflowJobState;
use Sassnowski\Venture\State\DefaultWorkflowState;
use Sassnowski\Venture\State\FakeWorkflowJobState;
use Sassnowski\Venture\State\FakeWorkflowState;
use Sassnowski\Venture\State\WorkflowStateStore;
use Sassnowski\Venture\Venture;

uses(TestCase::class);

beforeEach(function (): void {
    WorkflowStateStore::fake();
});

it('configures venture to use fake states', function (): void {
    expect(Venture::$workflowState)->toBe(FakeWorkflowState::class);
    expect(Venture::$workflowJobState)->toBe(FakeWorkflowJobState::class);
});

it('retrieves a default workflow state if no explicit state was configured', function (): void {
    $state = WorkflowStateStore::forWorkflow(new Workflow(['id' => 1]));

    expect($state)->toBeInstanceOf(FakeWorkflowState::class);
});

it('retrieves a default workflow job state if no explicit state was configured', function (): void {
    $state = WorkflowStateStore::forJob('::job-id::');

    expect($state)->toBeInstanceOf(FakeWorkflowJobState::class);
});

it('manages state for same workflow as singleton', function (): void {
    $state1 = WorkflowStateStore::forWorkflow(new Workflow(['id' => 1]));
    $state2 = WorkflowStateStore::forWorkflow(new Workflow(['id' => 1]));

    expect($state1)->toBe($state2);
});

it('manages state for same job as singleton', function (): void {
    $state1 = WorkflowStateStore::forJob('::job-id::');
    $state2 = WorkflowStateStore::forJob('::job-id::');

    expect($state1)->toBe($state2);
});

it('can setup specific states for jobs', function (): void {
    $state = new FakeWorkflowJobState(gated: true, canRun: true);

    WorkflowStateStore::setupJobs([
        '::job-id::' => $state,
    ]);

    expect(WorkflowStateStore::forJob('::job-id::'))->toBe($state);
    expect(WorkflowStateStore::forJob('::different-job-id::'))->not()->toBe($state);
});

it('can setup a specific state for a workflow', function (): void {
    $state = new FakeWorkflowState(allJobsFinished: true);

    WorkflowStateStore::setupWorkflow(new Workflow(['id' => 1]), $state);

    expect(WorkflowStateStore::forWorkflow(new Workflow(['id' => 1])))->toBe($state);
    expect(WorkflowStateStore::forWorkflow(new Workflow(['id' => 2])))->not()->toBe($state);
});

it('can be restored', function (): void {
    $workflowState = WorkflowStateStore::forWorkflow(new Workflow(['id' => 1]));
    $jobState = WorkflowStateStore::forJob('::job-id::');

    WorkflowStateStore::restore();

    expect(WorkflowStateStore::forWorkflow(new Workflow(['id' => 1])))
        ->not()->toBe($workflowState);
    expect(WorkflowStateStore::forJob('::job-id::'))
        ->not()->toBe($jobState);

    WorkflowStateStore::restore();

    expect(Venture::$workflowState)->toBe(DefaultWorkflowState::class);
    expect(Venture::$workflowJobState)->toBe(DefaultWorkflowJobState::class);
});
