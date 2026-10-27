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
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Stubs\EncryptedJob;

uses(TestCase::class);

it('completes encrypted workflow jobs', function (): void {
    config(['app.key' => \str_repeat('a', 32)]);

    $workflow = createWorkflow(['job_count' => 1]);
    $job = (new EncryptedJob())
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid())
        ->onConnection('sync');

    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'name' => $job->getJobId(),
        'job' => app(WorkflowJobSerializer::class)->serialize($job),
    ]);

    $step->start();

    expect($step->fresh()->isFinished())->toBeTrue()
        ->and($workflow->fresh()->isFinished())->toBeTrue()
        ->and($workflow->fresh()->jobs_processed)->toBe(1);
});

it('records failures from encrypted workflow jobs', function (): void {
    config(['app.key' => \str_repeat('a', 32)]);

    $workflow = createWorkflow(['job_count' => 1]);
    $job = (new EncryptedJob(shouldFail: true))
        ->withWorkflowId($workflow->id)
        ->withStepId(Str::uuid())
        ->onConnection('sync');

    $step = createWorkflowJob($workflow, [
        'uuid' => $job->getStepId(),
        'name' => $job->getJobId(),
        'job' => app(WorkflowJobSerializer::class)->serialize($job),
    ]);

    expect(fn () => $step->start())->toThrow(RuntimeException::class, 'The retry failed.');

    expect($step->fresh()->hasFailed())->toBeTrue()
        ->and($step->fresh()->exception)->toContain('The retry failed.')
        ->and($workflow->fresh()->jobs_failed)->toBe(1)
        ->and($workflow->fresh()->jobs_processed)->toBe(0);
});
