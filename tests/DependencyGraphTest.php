<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\TestJob2;
use Stubs\TestJob3;
use Stubs\TestJob4;
use Stubs\TestJob5;
use Stubs\TestJob6;
use function PHPUnit\Framework\assertEquals;
use Sassnowski\Venture\Graph\DependencyGraph;
use Sassnowski\Venture\Exceptions\UnresolvableDependenciesException;

beforeEach(function () {
    $this->graph = new DependencyGraph();
});

it('returns no dependencies if a job has none', function () {
    $job = new TestJob1();

    $this->graph->addDependantJob($job, []);

    assertEquals([], $this->graph->getDependencies($job));
});

it('returns the jobs direct dependencies', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();

    $this->graph->addDependantJob($job1, []);
    $this->graph->addDependantJob($job2, [TestJob1::class]);
    $this->graph->addDependantJob($job3, [TestJob1::class, TestJob2::class]);

    assertEquals([TestJob1::class, TestJob2::class], $this->graph->getDependencies($job3));
});

it('returns the instances of all dependants of a job', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();
    $job3 = new TestJob3();

    $this->graph->addDependantJob($job2, []);
    $this->graph->addDependantJob($job1, [TestJob2::class]);
    $this->graph->addDependantJob($job3, [TestJob2::class]);

    assertEquals([$job1, $job3], $this->graph->getDependantJobs($job2));
});

it('returns all jobs without dependencies', function () {
    $job1 = new TestJob1();
    $job2 = new TestJob2();

    $this->graph->addDependantJob($job1, []);
    $this->graph->addDependantJob($job2, [TestJob1::class]);

    assertEquals([$job1], $this->graph->getJobsWithoutDependencies());
});

it('throws an exception when trying to add a job with a dependency that does not exist', function () {
    test()->expectException(UnresolvableDependenciesException::class);
    test()->expectExceptionMessage(sprintf(
        'Unable to resolve dependency [%s]. Make sure it was added before declaring it as a dependency.',
        TestJob1::class
    ));

    $this->graph->addDependantJob(new TestJob2(), [TestJob1::class]);
});

it('can connect another graph to a single dependency in the current graph', function () {
    $graph1 = new DependencyGraph([
        TestJob1::class => [
            'instance' => new TestJob1(),
            'in_edges' => [],
            'out_edges' => [TestJob2::class],
        ],
        TestJob2::class => [
            'instance' => $job2 = new TestJob2(),
            'in_edges' => [TestJob1::class],
            'out_edges' => [],
        ],
        TestJob3::class => [
            'instance' => new TestJob3(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);
    $graph2 = new DependencyGraph([
        TestJob4::class => [
            'instance' => $job4 = new TestJob4(),
            'in_edges' => [],
            'out_edges' => [TestJob5::class],
        ],
        TestJob5::class => [
            'instance' => new TestJob5(),
            'in_edges' => [TestJob4::class],
            'out_edges' => [],
        ],
        TestJob6::class => [
            'instance' => $job6 = new TestJob6(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);

    $graph1->connectGraph($graph2, '::id::', [TestJob1::class]);

    assertEquals([TestJob1::class], $graph1->getDependencies(TestJob4::class));
    assertEquals([TestJob4::class], $graph1->getDependencies(TestJob5::class));
    assertEquals([TestJob1::class], $graph1->getDependencies(TestJob6::class));
    assertEquals([$job2, $job4, $job6], $graph1->getDependantJobs(TestJob1::class));
});

it('can connect a graph to multiple dependencies in the current graph', function () {
    $graph1 = new DependencyGraph([
        TestJob1::class => [
            'instance' => new TestJob1(),
            'in_edges' => [],
            'out_edges' => [TestJob2::class],
        ],
        TestJob2::class => [
            'instance' => $job2 = new TestJob2(),
            'in_edges' => [TestJob1::class],
            'out_edges' => [],
        ],
        TestJob3::class => [
            'instance' => new TestJob3(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);
    $graph2 = new DependencyGraph([
        TestJob4::class => [
            'instance' => $job4 = new TestJob4(),
            'in_edges' => [],
            'out_edges' => [TestJob5::class],
        ],
        TestJob5::class => [
            'instance' => new TestJob5(),
            'in_edges' => [TestJob4::class],
            'out_edges' => [],
        ],
        TestJob6::class => [
            'instance' => $job6 = new TestJob6(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);

    $graph1->connectGraph($graph2, '::id::', [TestJob2::class, TestJob3::class]);

    assertEquals([TestJob2::class, TestJob3::class], $graph1->getDependencies(TestJob4::class));
    assertEquals([TestJob2::class, TestJob3::class], $graph1->getDependencies(TestJob6::class));
});

it('can add a different graph without dependencies', function () {
    $graph1 = new DependencyGraph([
        TestJob1::class => [
            'instance' => $job1 = new TestJob1(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);
    $graph2 = new DependencyGraph([
        TestJob2::class => [
            'instance' => $job2 = new TestJob2(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);

    $graph1->connectGraph($graph2, '::id::', []);

    assertEquals([$job1, $job2], $graph1->getJobsWithoutDependencies());
});

it('can add a job with a dependency on a nested workflow', function () {
    $graph1 = new DependencyGraph([
        TestJob1::class => [
            'instance' => new TestJob1(),
            'in_edges' => [],
            'out_edges' => [],
        ],
    ]);
    $graph2 = new DependencyGraph([
        TestJob2::class => [
            'instance' => new TestJob2(),
            'in_edges' => [],
            'out_edges' => [TestJob4::class],
        ],
        TestJob3::class => [
            'instance' => new TestJob3(),
            'in_edges' => [],
            'out_edges' => [],
        ],
        TestJob4::class => [
            'instance' => new TestJob4(),
            'in_edges' => [TestJob2::class],
            'out_edges' => [],
        ],
    ]);
    $graph1->connectGraph($graph2, '::workflow-id::', [TestJob1::class]);

    $graph1->addDependantJob(new TestJob5(), ['::workflow-id::']);

    assertEquals([TestJob3::class, TestJob4::class], $graph1->getDependencies(TestJob5::class));
});
