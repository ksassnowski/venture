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

use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Listeners\AssociateEntityWithWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Stubs\EntityAwareTestWorkflow;
use Stubs\TestModel;
use Stubs\TestWorkflow;

uses(TestCase::class);

beforeEach(function (): void {
    $this->listener = new AssociateEntityWithWorkflow();
});

it('associates the workflowable with the workflow if the workflow implements the interface', function (): void {
    $entity = new TestModel(['id' => 5]);
    $workflow = new EntityAwareTestWorkflow($entity);
    $model = new Workflow();
    $event = new WorkflowCreating($workflow->definition(), $model);

    ($this->listener)($event);

    expect($model->workflowable)->toBe($entity);
});

it('does not associate a model with the workflow if the workflow does not implement the interface', function (): void {
    $workflow = new TestWorkflow();
    $model = new Workflow();
    $event = new WorkflowCreating($workflow->definition(), $model);

    ($this->listener)($event);

    expect($model->workflowable)->toBeNull();
});
