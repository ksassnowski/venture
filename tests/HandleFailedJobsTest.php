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

use Sassnowski\Venture\Actions\HandleFailedJobs;
use Stubs\TestJob1;

uses(TestCase::class);

beforeEach(function (): void {
    $this->action = new HandleFailedJobs();
    $_SERVER['__catch.count'] = 0;
});

it('marks the step as failed', function (): void {
    [$workflow, $initialJobs] = createDefinition()
        ->addJob($job = new TestJob1())
        ->build();

    ($this->action)($job, new Exception());

    expect($job->step())->hasFailed()->toBeTrue();
    expect($workflow->fresh())->jobs_failed->toBe(1);
});

it('runs the catch callback', function (): void {
    createDefinition()
        ->addJob($job = new TestJob1())
        ->catch(function (): void {
            ++$_SERVER['__catch.count'];
        })
        ->build();

    ($this->action)($job, new Exception());

    expect($_SERVER['__catch.count'])->toBe(1);
});
