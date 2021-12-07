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

use Sassnowski\Venture\UnserializeJobExtractor;
use Stubs\NonWorkflowJob;
use Stubs\TestJob1;

it('extracts a serialized workflow job from a Laravel queue job', function (): void {
    $workflowJob = new TestJob1();
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor();

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toEqual($workflowJob);
});

it('returns null if the command is not a workflow job', function (): void {
    $queueJob = createQueueJob(new NonWorkflowJob());
    $extractor = new UnserializeJobExtractor();

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toBeNull();
});
