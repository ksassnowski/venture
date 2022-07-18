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

namespace Sassnowski\Venture\Plugin;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Sassnowski\Venture\Events\JobAdded;
use Sassnowski\Venture\Events\JobAdding;
use Sassnowski\Venture\Events\JobCreated;
use Sassnowski\Venture\Events\JobCreating;
use Sassnowski\Venture\Events\JobFailed;
use Sassnowski\Venture\Events\JobFinished;
use Sassnowski\Venture\Events\JobProcessing;
use Sassnowski\Venture\Events\WorkflowAdded;
use Sassnowski\Venture\Events\WorkflowAdding;
use Sassnowski\Venture\Events\WorkflowCreated;
use Sassnowski\Venture\Events\WorkflowCreating;
use Sassnowski\Venture\Events\WorkflowFinished;
use Sassnowski\Venture\Events\WorkflowStarted;

final class PluginContext
{
    public function __construct(private Dispatcher $dispatcher)
    {
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobAdding): void|string $listener
     */
    public function onJobAdding(string|array|Closure $listener): self
    {
        return $this->registerListener(JobAdding::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobAdded): void|string $listener
     */
    public function onJobAdded(string|array|Closure $listener): self
    {
        return $this->registerListener(JobAdded::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowAdding): void|string $listener
     */
    public function onWorkflowAdding(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowAdding::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowAdded): void|string $listener
     */
    public function onWorkflowAdded(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowAdded::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowCreating): void|string $listener
     */
    public function onWorkflowCreating(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowCreating::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowCreated): void|string $listener
     */
    public function onWorkflowCreated(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowCreated::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobCreating): void|string $listener
     */
    public function onJobCreating(string|array|Closure $listener): self
    {
        return $this->registerListener(JobCreating::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobCreated): void|string $listener
     */
    public function onJobCreated(string|array|Closure $listener): self
    {
        return $this->registerListener(JobCreated::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobProcessing): void|string $listener
     */
    public function onJobProcessing(string|array|Closure $listener): self
    {
        return $this->registerListener(JobProcessing::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobFinished): void|string $listener
     */
    public function onJobFinished(string|array|Closure $listener): self
    {
        return $this->registerListener(JobFinished::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(JobFailed): void|string $listener
     */
    public function onJobFailed(string|array|Closure $listener): self
    {
        return $this->registerListener(JobFailed::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowStarted): void|string $listener
     */
    public function onWorkflowStarted(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowStarted::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|Closure(WorkflowFinished): void|string $listener
     */
    public function onWorkflowFinished(string|array|Closure $listener): self
    {
        return $this->registerListener(WorkflowFinished::class, $listener);
    }

    /**
     * @template T
     *
     * @param class-string<T>                                                  $event
     * @param array{0: class-string|object, 1: string}|Closure(T): void|string $listener
     */
    private function registerListener(string $event, string|array|Closure $listener): self
    {
        $this->dispatcher->listen($event, $listener);

        return $this;
    }
}
