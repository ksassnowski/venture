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

use Illuminate\Support\Str;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\Listeners\SetWorkflowJobProperties;
use Sassnowski\Venture\Models\Workflow;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;

uses(TestCase::class);

beforeEach(function (): void {
    $this->listener = new SetWorkflowJobProperties();
});

it('sets the workflow id on each job', function (): void {
    $definition = createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob($job2 = new TestJob2())
        ->addJob($job3 = new TestJob3(), [TestJob1::class]);
    $model = new Workflow(['id' => 5]);
    $event = new WorkflowCreated($definition, $model);

    ($this->listener)($event);

    expect($job1)->workflowId->toBe(5);
    expect($job2)->workflowId->toBe(5);
    expect($job3)->workflowId->toBe(5);
});

it('sets the dependencies on each job', function (): void {
    $definition = createDefinition()
        ->addJob($job1 = new TestJob1())
        ->addJob($job2 = new TestJob2())
        ->addJob($job3 = new TestJob3(), [TestJob1::class])
        ->addJob($job4 = new TestJob4(), [TestJob2::class, TestJob3::class]);
    $model = new Workflow(['id' => 5]);
    $event = new WorkflowCreated($definition, $model);

    ($this->listener)($event);

    expect($job1)->getDependencies()->toBeEmpty();
    expect($job2)->getDependencies()->toBeEmpty();
    expect($job3)->getDependencies()->toEqual([TestJob1::class]);
    expect($job4)->getDependencies()->toEqual([TestJob2::class, TestJob3::class]);
});

it('sets the dependent jobs on each job', function (): void {
    $definition = createDefinition()
        ->addJob($job1 = (new TestJob1())->withStepId(Str::orderedUuid()))
        ->addJob($job2 = (new TestJob2())->withStepId(Str::orderedUuid()))
        ->addJob($job3 = (new TestJob3())->withStepId(Str::orderedUuid()), [TestJob1::class])
        ->addJob($job4 = (new TestJob4())->withStepId(Str::orderedUuid()), [TestJob1::class, TestJob2::class]);
    $model = new Workflow(['id' => 5]);
    $event = new WorkflowCreated($definition, $model);

    ($this->listener)($event);

    expect($job1)->getDependantJobs()->toEqual([$job3->getStepId(), $job4->getStepId()]);
    expect($job2)->getDependantJobs()->toEqual([$job4->getStepId()]);
    expect($job3)->getDependantJobs()->toBeEmpty();
    expect($job4)->getDependantJobs()->toBeEmpty();
});
