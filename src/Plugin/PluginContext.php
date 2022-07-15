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
     * @param callable(JobAdding): void $listener
     */
    public function onJobAdding(callable $listener): self
    {
        return $this->registerListener(JobAdding::class, $listener);
    }

    /**
     * @param callable(JobAdded): void $listener
     */
    public function onJobAdded(callable $listener): self
    {
        return $this->registerListener(JobAdded::class, $listener);
    }

    /**
     * @param callable(WorkflowAdding): void $listener
     */
    public function onWorkflowAdding(callable $listener): self
    {
        return $this->registerListener(WorkflowAdding::class, $listener);
    }

    /**
     * @param callable(WorkflowAdded): void $listener
     */
    public function onWorkflowAdded(callable $listener): self
    {
        return $this->registerListener(WorkflowAdded::class, $listener);
    }

    /**
     * @param callable(WorkflowCreating): void $listener
     */
    public function onWorkflowCreating(callable $listener): self
    {
        return $this->registerListener(WorkflowCreating::class, $listener);
    }

    /**
     * @param callable(WorkflowCreated): void $listener
     */
    public function onWorkflowCreated(callable $listener): self
    {
        return $this->registerListener(WorkflowCreated::class, $listener);
    }

    /**
     * @param callable(JobCreating): void $listener
     */
    public function onJobCreating(callable $listener): self
    {
        return $this->registerListener(JobCreating::class, $listener);
    }

    /**
     * @param callable(JobCreated): void $listener
     */
    public function onJobCreated(callable $listener): self
    {
        return $this->registerListener(JobCreated::class, $listener);
    }

    /**
     * @param callable(JobProcessing): void $listener
     */
    public function onJobProcessing(callable $listener): self
    {
        return $this->registerListener(JobProcessing::class, $listener);
    }

    /**
     * @param callable(JobFinished): void $listener
     */
    public function onJobFinished(callable $listener): self
    {
        return $this->registerListener(JobFinished::class, $listener);
    }

    /**
     * @param callable(JobFailed): void $listener
     */
    public function onJobFailed(callable $listener): self
    {
        return $this->registerListener(JobFailed::class, $listener);
    }

    /**
     * @param callable(WorkflowStarted): void $listener
     */
    public function onWorkflowStarted(callable $listener): self
    {
        return $this->registerListener(WorkflowStarted::class, $listener);
    }

    /**
     * @param callable(WorkflowFinished): void $listener
     */
    public function onWorkflowFinished(callable $listener): self
    {
        return $this->registerListener(WorkflowFinished::class, $listener);
    }

    /**
     * @template T
     *
     * @param class-string<T>   $event
     * @param callable(T): void $listener
     */
    private function registerListener(string $event, callable $listener): self
    {
        $this->dispatcher->listen($event, Closure::fromCallable($listener));

        return $this;
    }
}
