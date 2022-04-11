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

use Illuminate\Support\Facades\DB;
use Sassnowski\Venture\Models\Workflow;
use Stubs\TestJobWithNonPublicProperties;
use Stubs\WorkflowWithDatabaseJob;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;

uses(TestCase::class);

it('can serialize job with protected and private properties', function () {
    $workflow = WorkflowWithDatabaseJob::start('foo', 'bar');

    assertInstanceOf(Workflow::class, $workflow);

    assertCount(1, $jobs = DB::table('jobs')->get());

    $payload = json_decode($jobs->first()->payload, true);

    $job = unserialize($payload['data']['command']);

    assertInstanceOf(TestJobWithNonPublicProperties::class, $job);
    
    assertEquals('foo', $job->getFoo());
    assertEquals('bar', $job->getBar());
});
