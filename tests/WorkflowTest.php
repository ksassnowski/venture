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

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sassnowski\Venture\Events\JobCreated;
use Sassnowski\Venture\Events\JobCreating;
use Stubs\TestJob1;
use Stubs\TestJob2;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertTrue;

uses(TestCase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

it('can fetch all of its failed jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob1 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $failedJob2 = createWorkflowJob($workflow, ['failed_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, ['failed_at' => null]);

    $actual = $workflow->failedJobs();

    assertCount(2, $actual);
    assertTrue($actual->contains($failedJob1));
    assertTrue($actual->contains($failedJob2));
});

it('can fetch all of its pending jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob = createWorkflowJob($workflow, ['failed_at' => now()]);
    $finishedJob = createWorkflowJob($workflow, ['finished_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, [
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $actual = $workflow->pendingJobs();

    assertCount(1, $actual);
    assertTrue($actual[0]->is($pendingJob));
});

it('can fetch all of its finished jobs', function (): void {
    $workflow = createWorkflow();
    $failedJob = createWorkflowJob($workflow, ['failed_at' => now()]);
    $finishedJob = createWorkflowJob($workflow, ['finished_at' => now()]);
    $pendingJob = createWorkflowJob($workflow, [
        'failed_at' => null,
        'finished_at' => null,
    ]);

    $actual = $workflow->finishedJobs();

    assertCount(1, $actual);
    assertTrue($actual[0]->is($finishedJob));
});

it('returns the dependency graph as an adjacency list', function (): void {
    Carbon::setTestNow('2020-11-11 11:11:00');
    $exception = new Exception();

    $workflow = createWorkflow();
    $job3 = createWorkflowJob($workflow, [
        'name' => '::job-3-name::',
    ]);
    $job2 = createWorkflowJob($workflow, [
        'name' => '::job-2-name::',
        'failed_at' => now()->timestamp,
        'exception' => (string) $exception,
        'edges' => [$job3->uuid],
    ]);
    $job1 = createWorkflowJob($workflow, [
        'name' => '::job-1-name::',
        'edges' => [
            $job2->uuid,
            $job3->uuid,
        ],
    ]);

    $adjacencyList = $workflow->asAdjacencyList();

    assertEquals([
        $job1->uuid => [
            'name' => '::job-1-name::',
            'finished_at' => null,
            'failed_at' => null,
            'exception' => null,
            'edges' => [
                $job2->uuid,
                $job3->uuid,
            ],
        ],
        $job2->uuid => [
            'name' => '::job-2-name::',
            'finished_at' => null,
            'failed_at' => now(),
            'exception' => (string) $exception,
            'edges' => [
                $job3->uuid,
            ],
        ],
        $job3->uuid => [
            'name' => '::job-3-name::',
            'finished_at' => null,
            'exception' => null,
            'failed_at' => null,
            'edges' => [],
        ],
    ], $adjacencyList);
});

it('fires an event for each job that gets added', function (): void {
    Event::fake([JobCreating::class]);
    $workflow = createWorkflow();
    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());

    $workflow->addJobs([$job1, $job2]);

    Event::assertDispatched(JobCreating::class, 2);
});

it('fires an event for each job that was created', function (): void {
    Event::fake([JobCreated::class]);
    $workflow = createWorkflow();
    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());

    $workflow->addJobs([$job1, $job2]);

    Event::assertDispatched(JobCreated::class, 2);
});
