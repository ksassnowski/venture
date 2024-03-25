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

namespace Sassnowski\Venture\Plugin;

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
     * @param array{0: class-string|object, 1: string}|\Closure(JobAdding): void $listener
     */
    public function onJobAdding(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobAdding::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobAdded): void $listener
     */
    public function onJobAdded(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobAdded::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowAdding): void $listener
     */
    public function onWorkflowAdding(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowAdding::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowAdded): void $listener
     */
    public function onWorkflowAdded(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowAdded::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowCreating): void $listener
     */
    public function onWorkflowCreating(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowCreating::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowCreated): void $listener
     */
    public function onWorkflowCreated(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowCreated::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobCreating): void $listener
     */
    public function onJobCreating(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobCreating::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobCreated): void $listener
     */
    public function onJobCreated(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobCreated::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobProcessing): void $listener
     */
    public function onJobProcessing(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobProcessing::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobFinished): void $listener
     */
    public function onJobFinished(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobFinished::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(JobFailed): void $listener
     */
    public function onJobFailed(array|\Closure|string $listener): self
    {
        return $this->registerListener(JobFailed::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowStarted): void $listener
     */
    public function onWorkflowStarted(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowStarted::class, $listener);
    }

    /**
     * @param array{0: class-string|object, 1: string}|\Closure(WorkflowFinished): void $listener
     */
    public function onWorkflowFinished(array|\Closure|string $listener): self
    {
        return $this->registerListener(WorkflowFinished::class, $listener);
    }

    /**
     * @template T
     *
     * @param class-string<T>                                            $event
     * @param array{0: class-string|object, 1: string}|\Closure(T): void $listener
     */
    private function registerListener(string $event, array|\Closure|string $listener): self
    {
        $this->dispatcher->listen($event, $listener);

        return $this;
    }
}
