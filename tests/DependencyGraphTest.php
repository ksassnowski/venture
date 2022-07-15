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

use Sassnowski\Venture\Exceptions\DuplicateJobException;
use Sassnowski\Venture\Exceptions\DuplicateWorkflowException;
use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;
use Sassnowski\Venture\Graph\DependencyGraph;
use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;
use Stubs\TestJob5;
use Stubs\TestJob6;
use function PHPUnit\Framework\assertEquals;

beforeEach(function (): void {
    $this->graph = new DependencyGraph();
});

it('returns no dependencies if a job has none', function (): void {
    $job = new TestJob1();

    $this->graph->addDependantJob($job, [], TestJob1::class);

    assertEquals([], $this->graph->getDependencies(TestJob1::class));
});

it('returns the jobs direct dependencies', function (): void {
    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();

    $this->graph->addDependantJob($job1, [], TestJob1::class);
    $this->graph->addDependantJob($job2, [TestJob1::class], TestJob2::class);
    $this->graph->addDependantJob($job3, [TestJob1::class, TestJob2::class], TestJob3::class);

    expect($this->graph->getDependencies(TestJob1::class))
        ->toBeEmpty();
    expect($this->graph->getDependencies(TestJob2::class))
        ->toEqual([TestJob1::class]);
    expect($this->graph->getDependencies(TestJob3::class))
        ->toEqual([TestJob1::class, TestJob2::class]);
});

it('returns the instances of all dependants of a job', function (): void {
    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();

    $this->graph->addDependantJob($job2, [], TestJob2::class);
    $this->graph->addDependantJob($job1, [TestJob2::class], TestJob1::class);
    $this->graph->addDependantJob($job3, [TestJob2::class], TestJob3::class);

    assertEquals([$job1, $job3], $this->graph->getDependantJobs(TestJob2::class));
});

it('returns all jobs without dependencies', function (): void {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    $this->graph->addDependantJob($job1, [], TestJob1::class);
    $this->graph->addDependantJob($job2, [TestJob1::class], TestJob2::class);

    assertEquals([$job1], $this->graph->getJobsWithoutDependencies());
});

it('throws an exception when trying to add a job with a dependency that does not exist', function (): void {
    test()->expectException(UnresolvableDependenciesException::class);
    test()->expectExceptionMessage(\sprintf(
        'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
        TestJob1::class,
    ));

    $this->graph->addDependantJob(new TestJob2(), [TestJob1::class], TestJob2::class);
});

it('can connect another graph to a single dependency in the current graph', function (): void {
    $graph1 = new DependencyGraph();
    $graph1->addDependantJob(new TestJob1(), [], TestJob1::class);
    $graph1->addDependantJob($job2 = new TestJob2(), [TestJob1::class], TestJob2::class);
    $graph1->addDependantJob(new TestJob3(), [], TestJob3::class);

    $graph2 = new DependencyGraph();
    $graph2->addDependantJob($job4 = new TestJob4(), [], TestJob4::class);
    $graph2->addDependantJob(new TestJob5(), [TestJob4::class], TestJob5::class);
    $graph2->addDependantJob($job6 = new TestJob6(), [], TestJob6::class);

    $graph1->connectGraph($graph2, '::id::', [TestJob1::class]);

    assertEquals([TestJob1::class], $graph1->getDependencies('::id::.' . TestJob4::class));
    assertEquals(['::id::.' . TestJob4::class], $graph1->getDependencies('::id::.' . TestJob5::class));
    assertEquals([TestJob1::class], $graph1->getDependencies('::id::.' . TestJob6::class));
    assertEquals([$job2, $job4, $job6], $graph1->getDependantJobs(TestJob1::class));
});

it('can connect a graph to multiple dependencies in the current graph', function (): void {
    $graph1 = new DependencyGraph();
    $graph1->addDependantJob(new TestJob1(), [], TestJob1::class);
    $graph1->addDependantJob(new TestJob2(), [TestJob1::class], TestJob2::class);
    $graph1->addDependantJob(new TestJob3(), [], TestJob3::class);

    $graph2 = new DependencyGraph();
    $graph2->addDependantJob(new TestJob4(), [], TestJob4::class);
    $graph2->addDependantJob(new TestJob5(), [TestJob4::class], TestJob5::class);
    $graph2->addDependantJob(new TestJob6(), [], TestJob6::class);

    $graph1->connectGraph($graph2, '::id::', [TestJob2::class, TestJob3::class]);

    expect($graph1->getDependencies('::id::.' . TestJob4::class))
        ->toEqual([TestJob2::class, TestJob3::class]);
    expect($graph1->getDependencies('::id::.' . TestJob6::class))
        ->toEqual([TestJob2::class, TestJob3::class]);
});

it('can add a different graph without dependencies', function (): void {
    $graph1 = new DependencyGraph();
    $graph1->addDependantJob($job1 = new TestJob1(), [], TestJob1::class);

    $graph2 = new DependencyGraph();
    $graph2->addDependantJob($job2 = new TestJob2(), [], TestJob2::class);

    $graph1->connectGraph($graph2, '::id::', []);

    expect($graph1->getJobsWithoutDependencies())
        ->toEqual([$job1, $job2]);
});

it('can add a job with a dependency on a nested workflow', function (): void {
    $graph1 = new DependencyGraph();
    $graph1->addDependantJob(new TestJob1(), [], TestJob1::class);

    $graph2 = new DependencyGraph();
    $graph2->addDependantJob(new TestJob2(), [], TestJob2::class);
    $graph2->addDependantJob(new TestJob3(), [], TestJob3::class);
    $graph2->addDependantJob(new TestJob4(), [TestJob2::class], TestJob4::class);

    $graph1->connectGraph($graph2, '::workflow-id::', [TestJob1::class]);

    $graph1->addDependantJob(new TestJob5(), ['::workflow-id::'], TestJob5::class);

    expect($graph1->getDependencies(TestJob5::class))
        ->toEqual([
            '::workflow-id::.' . TestJob3::class,
            '::workflow-id::.' . TestJob4::class,
        ]);
});

it('can add a nested workflow with a dependency on another nested workflow', function (): void {
    $graph1 = new DependencyGraph();

    $graph2 = new DependencyGraph();
    $graph2->addDependantJob(new TestJob1(), [], TestJob1::class);
    $graph2->addDependantJob(new TestJob2(), [], TestJob2::class);
    $graph2->addDependantJob(new TestJob3(), [TestJob1::class], TestJob3::class);

    $graph3 = new DependencyGraph();
    $graph3->addDependantJob(new TestJob4(), [], TestJob4::class);
    $graph3->addDependantJob(new TestJob5(), [TestJob4::class], TestJob5::class);
    $graph3->addDependantJob(new TestJob6(), [], TestJob6::class);

    $graph1->connectGraph($graph2, '::graph-2-id::', []);

    $graph1->connectGraph($graph3, '::graph-3-id::', ['::graph-2-id::']);

    expect($graph1->getDependencies('::graph-3-id::.' . TestJob4::class))
        ->toEqual([
            '::graph-2-id::.' . TestJob2::class,
            '::graph-2-id::.' . TestJob3::class,
        ]);
    expect($graph1->getDependencies('::graph-3-id::.' . TestJob5::class))
        ->toEqual(['::graph-3-id::.' . TestJob4::class]);
    expect($graph1->getDependencies('::graph-3-id::.' . TestJob6::class))
        ->toEqual([
            '::graph-2-id::.' . TestJob2::class,
            '::graph-2-id::.' . TestJob3::class,
        ]);
});

it('throws an exception when adding workflows with the same ids', function (): void {
    $graph1 = new DependencyGraph();
    $graph2 = new DependencyGraph();
    $graph2->addDependantJob(new TestJob1(), [], TestJob1::class);

    $graph1->connectGraph($graph2, id: '::id::', dependencies: []);
    $graph1->connectGraph($graph2, id: '::id::', dependencies: []);
})->expectException(DuplicateWorkflowException::class);

it('throws an exception when adding a job with an existing id', function (): void {
    $graph = new DependencyGraph();

    $graph->addDependantJob(new TestJob1(), [], TestJob1::class);
    $graph->addDependantJob(new TestJob1(), [], TestJob1::class);
})->expectException(DuplicateJobException::class);

it('allows adding multiple instances of the same job when providing explicit ids', function (): void {
    $graph = new DependencyGraph();

    $graph->addDependantJob(new TestJob1(), [], '::job-1-id::');
    $graph->addDependantJob(new TestJob1(), [], '::job-2-id::');

    expect($graph->getJobsWithoutDependencies())->toHaveCount(2);
});
