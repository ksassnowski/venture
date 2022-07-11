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

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Mockery as m;
use Sassnowski\Venture\Serializer\Base64WorkflowSerializer;
use Sassnowski\Venture\Serializer\UnserializeException;
use Sassnowski\Venture\WorkflowStepAdapter;
use Stubs\LegacyWorkflowJob;
use Stubs\TestJob1;

it('simply serializes workflow jobs if a non-postgres connection is used', function (): void {
    $connection = m::mock(MySqlConnection::class);
    $serializer = new Base64WorkflowSerializer($connection);
    $workflowJob = new TestJob1();

    $result = $serializer->serialize($workflowJob);

    expect($result)->toBe(\serialize($workflowJob));
});

it('base64 encodes workflow jobs if a postgres connection is used', function (): void {
    $connection = m::mock(PostgresConnection::class);
    $serializer = new Base64WorkflowSerializer($connection);
    $workflowJob = new TestJob1();

    $result = $serializer->serialize($workflowJob);

    expect($result)->toBe(\base64_encode(\serialize($workflowJob)));
});

it('simply unserializes workflow jobs if a non-postgres connection is used', function (): void {
    $connection = m::mock(MySqlConnection::class);
    $serializer = new Base64WorkflowSerializer($connection);
    $workflowJob = new TestJob1();
    $serializedJob = \serialize($workflowJob);

    $result = $serializer->unserialize($serializedJob);

    expect($result)->toEqual($workflowJob);
});

it('unserializes and base64 decodes the job if a postgres connection is used', function (): void {
    $connection = m::mock(PostgresConnection::class);
    $serializer = new Base64WorkflowSerializer($connection);
    $workflowJob = new TestJob1();
    $serializedJob = \base64_encode(\serialize($workflowJob));

    $result = $serializer->unserialize($serializedJob);

    expect($result)->toEqual($workflowJob);
});

it('does not base64 decode a job if it has not been encoded before even if using a postgres connection', function (): void {
    $connection = m::mock(PostgresConnection::class);
    $serializer = new Base64WorkflowSerializer($connection);
    $workflowJob = new TestJob1();
    $serializedJob = \serialize($workflowJob);

    $result = $serializer->unserialize($serializedJob);

    expect($result)->toEqual($workflowJob);
});

it('throws an exception if the job could not be unserialized', function (): void {
    $serializer = new Base64WorkflowSerializer(m::mock(ConnectionInterface::class));
    $serializer->unserialize('::not-a-valid-serialized-string::');
})->throws(UnserializeException::class);

it('wraps jobs that don\'t yet implement the WorkflowStepInterface with WorkflowStepAdapter', function (): void {
    $serializer = new Base64WorkflowSerializer(m::mock(ConnectionInterface::class));
    $job = new LegacyWorkflowJob();

    $result = $serializer->unserialize(\serialize($job));

    expect($result)->toBeInstanceOf(WorkflowStepAdapter::class);
});
