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

use Sassnowski\Venture\ClassNameStepIdGenerator;
use Sassnowski\Venture\WorkflowStepAdapter;
use Stubs\LegacyWorkflowJob;
use Stubs\TestJob1;

it('returns the jobs FQCN', function (): void {
    $generator = new ClassNameStepIdGenerator();

    $id = $generator->generateId(new TestJob1());

    expect($id)->toBe(TestJob1::class);
});

it('returns the wrapped objects class name when passed a WorkflowStepAdapter', function (): void {
    $generator = new ClassNameStepIdGenerator();
    $job = WorkflowStepAdapter::fromJob(new LegacyWorkflowJob());

    $id = $generator->generateId($job);

    expect($id)->toBe(LegacyWorkflowJob::class);
});
