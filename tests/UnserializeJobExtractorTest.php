<?php declare(strict_types=1);

use Stubs\TestJob1;
use Stubs\NonWorkflowJob;
use Sassnowski\Venture\UnserializeJobExtractor;

it('extracts a serialized workflow job from a Laravel queue job', function () {
    $workflowJob = new TestJob1();
    $queueJob = createQueueJob($workflowJob);
    $extractor = new UnserializeJobExtractor();

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toEqual($workflowJob);
});

it('returns null if the command is not a workflow job', function () {
    $queueJob = createQueueJob(new NonWorkflowJob());
    $extractor = new UnserializeJobExtractor();

    $actual = $extractor->extractWorkflowJob($queueJob);

    expect($actual)->toBeNull();
});