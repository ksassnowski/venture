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

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\JobExtractor;
use Sassnowski\Venture\Manager\WorkflowManager;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Sassnowski\Venture\StepIdGenerator;
use Sassnowski\Venture\UnserializeJobExtractor;
use Stubs\TestJob1;
use Stubs\TestJob2;

uses(TestCase::class);

beforeEach(function (): void {
    $_SERVER['__then_called'] = 0;
    $_SERVER['__catch_called'] = 0;
});

it('can handle old workflows that still saved serialized dependent jobs instead of step ids', function (): void {
    Bus::fake();

    $workflow = createWorkflow([
        'job_count' => 2,
        'jobs_processed' => 0,
    ]);
    $job1 = (new TestJob1())->withStepId(Str::orderedUuid());
    $job2 = (new TestJob2())->withStepId(Str::orderedUuid());
    $job1->dependantJobs = [$job2];
    $job2->withDependencies([TestJob1::class]);
    $workflow->addJobs(wrapJobsForWorkflow([$job1, $job2]));

    $workflow->onStepFinished($job1);

    Bus::assertDispatched(TestJob2::class);
});

it('can handle missing class keys in config', function (string $abstract, string $defaultClass): void {
    config([
        'venture' => [
            'workflow_table' => 'workflows',
            'jobs_table' => 'workflow_jobs',
        ],
    ]);

    expect(app($abstract))->toBeInstanceOf($defaultClass);
})->with([
    'workflow manager' => [
        'venture.manager',
        WorkflowManager::class,
    ],
    'job extractor' => [
        JobExtractor::class,
        UnserializeJobExtractor::class,
    ],
    'workflow step id generator' => [
        StepIdGenerator::class,
        ClassNameStepIdGenerator::class,
    ],
    'workflow serializer' => [
        WorkflowJobSerializer::class,
        Base64WorkflowSerializer::class,
    ],
]);

it('can handle old workflows that still use opis/closure for their then_callback', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
        'then_callback' => \serialize(SerializableClosure::from(function (): void {
            ++$_SERVER['__then_called'];
        })),
    ]);
    $job = (new TestJob1())->withStepId(Str::orderedUuid());
    $workflow->addJobs(wrapJobsForWorkflow([$job]));

    $workflow->onStepFinished($job);

    expect($_SERVER['__then_called'])->toBe(1);
});

it('can handle old workflows that still use opis/closure for their catch_callback', function (): void {
    $workflow = createWorkflow([
        'job_count' => 1,
        'jobs_processed' => 0,
        'catch_callback' => \serialize(SerializableClosure::from(function (): void {
            ++$_SERVER['__catch_called'];
        })),
    ]);

    $workflow->onStepFailed(new TestJob1(), new Exception());

    expect($_SERVER['__catch_called'])->toBe(1);
});
