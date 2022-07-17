<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

use PHPUnit\Framework\AssertionFailedError;
use Sassnowski\Venture\Dispatcher\FakeDispatcher;
use Stubs\TestJob1;
use Stubs\TestJob2;

beforeEach(function (): void {
    $this->dispatcher = new FakeDispatcher();
});

test('assertJobWasDispatched passes if a job with the same id was dispatched', function (): void {
    $this->dispatcher->dispatch([new TestJob1(), (new TestJob2())->withJobId('::job-id::')]);

    $this->dispatcher->assertJobWasDispatched(TestJob1::class);
    $this->dispatcher->assertJobWasDispatched('::job-id::');
});

test('assertJobWasDispatched fails if no job with the same id was dispatched', function (): void {
    $this->dispatcher->dispatch([new TestJob1()]);

    $this->dispatcher->assertJobWasDispatched(TestJob2::class);
})->throws(AssertionFailedError::class);

test('assertJobWasDispatched fails if a job with the same id was dispatched but on a different connection', function (): void {
    $this->dispatcher->dispatch([(new TestJob1())->withConnection('::connection::')]);

    $this->dispatcher->assertJobWasDispatched(TestJob1::class, '::different-connection::');
})->throws(AssertionFailedError::class);

test('assertJobWasDispatched passes if a job with the same id was dispatched on the same connection', function (): void {
    $this->dispatcher->dispatch([(new TestJob1())->withConnection('::connection::')]);

    $this->dispatcher->assertJobWasDispatched(TestJob1::class, '::connection::');
});

test('assertJobWasNotDispatched passes if no job with the same id was dispatched', function (): void {
    $this->dispatcher->dispatch([new TestJob1(), (new TestJob2())->withJobId('::job-id::')]);

    $this->dispatcher->assertJobWasNotDispatched(TestJob2::class);
    $this->dispatcher->assertJobWasNotDispatched('::different-job-id::');
});

test('assertJobWasNotDispatched fails if a job with the same id was dispatched', function (): void {
    $this->dispatcher->dispatch([new TestJob1()]);

    $this->dispatcher->assertJobWasNotDispatched(TestJob1::class);
})->throws(AssertionFailedError::class);

test('assertDependentJobsDispatchedFor passes if the dependent jobs for a job with the same id were dispatched', function (): void {
    $this->dispatcher->dispatchDependentJobs(new TestJob1());
    $this->dispatcher->dispatchDependentJobs((new TestJob2())->withJobId('::job-id::'));

    $this->dispatcher->assertDependentJobsDispatchedFor(TestJob1::class);
    $this->dispatcher->assertDependentJobsDispatchedFor('::job-id::');
});

test('assertDependentJobsDispatchedFor fails if the dependent jobs for a job with the same id were not dispatched', function (): void {
    $this->dispatcher->dispatchDependentJobs(new TestJob1());

    $this->dispatcher->assertDependentJobsDispatchedFor(TestJob2::class);
})->throws(AssertionFailedError::class);

test('assertDependentJobsNotDispatchedFor passes if the dependent jobs for a job with the same id were not dispatched', function (): void {
    $this->dispatcher->dispatchDependentJobs(new TestJob1());

    $this->dispatcher->assertDependentJobsNotDispatchedFor(TestJob2::class);
});

test('assertDependentJobsNotDispatchedFor fails if the dependent jobs for a job with the same id were dispatched', function (): void {
    $this->dispatcher->dispatchDependentJobs(new TestJob1());

    $this->dispatcher->assertDependentJobsNotDispatchedFor(TestJob1::class);
})->throws(AssertionFailedError::class);
