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

use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Models\WorkflowJob;
use Sassnowski\Venture\Venture;
use Stubs\CustomWorkflowJobModel;
use Stubs\CustomWorkflowModel;
use Stubs\WorkflowWithJob;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertNotInstanceOf;

uses(TestCase::class);

afterEach(function () {
    Venture::useWorkflowModel(Workflow::class);
    Venture::useWorkflowJobModel(WorkflowJob::class);
});

it('can override workflow model', function () {
    Venture::useWorkflowModel(CustomWorkflowModel::class);

    assertInstanceOf(CustomWorkflowModel::class, WorkflowWithJob::start());
});

it('can override workflow job model', function () {
    Venture::useWorkflowJobModel(CustomWorkflowJobModel::class);

    assertInstanceOf(Workflow::class, $workflow = WorkflowWithJob::start());
    assertCount(1, $jobs = $workflow->jobs()->get());
    assertInstanceOf(CustomWorkflowJobModel::class, $job = $jobs->first());
    assertInstanceOf(Workflow::class, $job->workflow);
    assertNotInstanceOf(CustomWorkflowModel::class, $job->workflow);
});

it('can override workflow and job model', function () {
    Venture::useWorkflowModel(CustomWorkflowModel::class);
    Venture::useWorkflowJobModel(CustomWorkflowJobModel::class);
    
    assertInstanceOf(CustomWorkflowModel::class, $workflow = WorkflowWithJob::start());
    assertCount(1, $jobs = $workflow->jobs()->get());
    assertInstanceOf(CustomWorkflowJobModel::class, $job = $jobs->first());
    assertInstanceOf(CustomWorkflowModel::class, $job->workflow);
});
