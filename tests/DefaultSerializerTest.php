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

use Sassnowski\Venture\Serializer\DefaultSerializer;
use Sassnowski\Venture\Serializer\UnserializeException;
use Sassnowski\Venture\WorkflowStepAdapter;
use Stubs\LegacyWorkflowJob;
use Stubs\TestJob1;

it('serializes the object', function (): void {
    $serializer = new DefaultSerializer();
    $job = new TestJob1();

    $result = $serializer->serialize($job);

    expect($result)->toBe(\serialize($job));
});

it('unserializes the object', function (): void {
    $serializer = new DefaultSerializer();
    $job = new TestJob1();

    $result = $serializer->unserialize(\serialize($job));

    expect($result)->toEqual($job);
});

it('throws an exception if the provided string could not be unserialized', function (): void {
    (new DefaultSerializer())->unserialize('::not-a-valid-serialized-string::');
})->throws(UnserializeException::class);

it('wraps jobs that don\'t yet implement the WorkflowStepInterface with WorkflowStepAdapter', function (): void {
    $serializer = new DefaultSerializer();
    $job = new LegacyWorkflowJob();

    $result = $serializer->unserialize(\serialize($job));

    expect($result)->toBeInstanceOf(WorkflowStepAdapter::class);
});
