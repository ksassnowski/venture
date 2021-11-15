<?php declare(strict_types=1);

/**
 * Copyright (c) 2021 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use Sassnowski\Venture\DTO\Workflow;
use Sassnowski\Venture\Persistence\Database\DatabaseWorkflowRepository;

uses(TestCase::class);

beforeEach(function () {
    $this->repository = resolve(DatabaseWorkflowRepository::class);
});

it('returns a hydrated workflow model if a workflow exists for the given id', function (Closure $createWorkflow, array $expectedCounts) {
    $workflowModel = $createWorkflow();

    $result = $this->repository->find($workflowModel->id);

    expect($result)->toBeInstanceOf(Workflow::class);
    expect($result)->id->toEqual($workflowModel->id);
    expect($result)->jobCount->toBe($expectedCounts['jobCount']);
    expect($result)->failedJobsCount->toBe($expectedCounts['failedJobsCount']);
    expect($result)->processedJobsCount->toBe($expectedCounts['processedJobsCount']);
})->with([
    [
        fn () => fn () => createWorkflow(),
        [
            'jobCount' => 0,
            'failedJobsCount' => 0,
            'processedJobsCount' => 0,
        ],
    ],
    [
        fn () => fn () => createWorkflow(['job_count' => 2]),
        [
            'jobCount' => 2,
            'failedJobsCount' => 0,
            'processedJobsCount' => 0,
        ],
    ],
    [
        fn () => fn () => createWorkflow(['job_count' => 2, 'jobs_processed' => 1]),
        [
            'jobCount' => 2,
            'failedJobsCount' => 0,
            'processedJobsCount' => 1,
        ],
    ],
    [
        fn () => fn () => createWorkflow(['job_count' => 2, 'jobs_processed' => 1, 'jobs_failed' => 1]),
        [
            'jobCount' => 2,
            'failedJobsCount' => 1,
            'processedJobsCount' => 1,
        ],
    ]
]);

it('returns null if no workflow exists for the given id', function () {
    $result = $this->repository->find(1);

    expect($result)->toBeNull();
});