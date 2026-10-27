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

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter as EncrypterContract;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Encryption\Encrypter;
use Sassnowski\Venture\Serializer\DefaultSerializer;
use Sassnowski\Venture\Serializer\WorkflowJobSerializer;
use Sassnowski\Venture\UnserializeJobExtractor;
use Sassnowski\Venture\WorkflowStepAdapter;
use Stubs\LegacyWorkflowJob;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;

uses(TestCase::class);

it('extracts a serialized workflow job from a Laravel queue job', function (): void {
    $workflowJob = new TestJob1();
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor(new DefaultSerializer());

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toEqual($workflowJob);
});

it('returns null if the command is not a workflow job', function (): void {
    $queueJob = createQueueJob(new NonWorkflowJob());
    $serializer = Mockery::mock(WorkflowJobSerializer::class);
    $serializer->shouldNotReceive('unserialize');
    $extractor = new UnserializeJobExtractor($serializer);

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toBeNull();
});

it('extracts jobs that use the workflow step trait without implementing the interface', function (): void {
    $workflowJob = new LegacyWorkflowJob();
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor(new DefaultSerializer());

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toBeInstanceOf(WorkflowStepAdapter::class)
        ->and($actual->unwrap())->toEqual($workflowJob);
});

it('extracts wrapped workflow steps', function (): void {
    $workflowJob = WorkflowStepAdapter::fromJob(new LegacyWorkflowJob());
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor(new DefaultSerializer());

    expect($extractor->extractWorkflowJob($queueJob))->toEqual($workflowJob);
});

it('decrypts encrypted commands before unserializing them', function (): void {
    $encrypter = new Encrypter(\str_repeat('a', 32), 'AES-256-CBC');
    app()->instance(EncrypterContract::class, $encrypter);
    $workflowJob = new TestJob1();
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'data' => [
            'commandName' => TestJob1::class,
            'command' => $encrypter->encrypt(\serialize($workflowJob)),
        ],
    ]);

    $extractor = new UnserializeJobExtractor(new DefaultSerializer());

    expect($extractor->extractWorkflowJob($queueJob))->toEqual($workflowJob);
});

it('does not decrypt unencrypted commands when an encrypter is available', function (): void {
    $encrypter = new Encrypter(\str_repeat('a', 32), 'AES-256-CBC');
    app()->instance(EncrypterContract::class, $encrypter);
    $workflowJob = new TestJob1();
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor(new DefaultSerializer());

    expect($extractor->extractWorkflowJob($queueJob))->toEqual($workflowJob);
});

it('does not unserialize commands that cannot be decrypted', function (): void {
    $encrypter = new Encrypter(\str_repeat('a', 32), 'AES-256-CBC');
    app()->instance(EncrypterContract::class, $encrypter);
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'data' => [
            'commandName' => TestJob1::class,
            'command' => 'invalid encrypted payload',
        ],
    ]);
    $serializer = Mockery::mock(WorkflowJobSerializer::class);
    $serializer->shouldNotReceive('unserialize');
    $extractor = new UnserializeJobExtractor($serializer);

    expect(fn () => $extractor->extractWorkflowJob($queueJob))->toThrow(DecryptException::class);
});

it('does not decrypt or unserialize encrypted non-workflow jobs', function (): void {
    $encrypter = Mockery::mock(EncrypterContract::class);
    $encrypter->shouldNotReceive('decrypt');
    app()->instance(EncrypterContract::class, $encrypter);

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('payload')->andReturn([
        'data' => [
            'commandName' => NonWorkflowJob::class,
            'command' => 'encrypted job',
        ],
    ]);
    $serializer = Mockery::mock(WorkflowJobSerializer::class);
    $serializer->shouldNotReceive('unserialize');

    $extractor = new UnserializeJobExtractor($serializer);

    expect($extractor->extractWorkflowJob($queueJob))->toBeNull();
});

it('ignores queue payloads without a resolvable command class', function (array $payload): void {
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('payload')->andReturn($payload);
    $serializer = Mockery::mock(WorkflowJobSerializer::class);
    $serializer->shouldNotReceive('unserialize');
    $extractor = new UnserializeJobExtractor($serializer);

    expect($extractor->extractWorkflowJob($queueJob))->toBeNull();
})->with([
    'string handler' => [['job' => 'App\\Jobs\\Handler@fire', 'data' => []]],
    'missing class' => [['data' => ['commandName' => 'MissingWorkflowJob', 'command' => 'serialized job']]],
]);
