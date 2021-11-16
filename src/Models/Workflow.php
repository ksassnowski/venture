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

namespace Sassnowski\Venture\Models;

use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Opis\Closure\SerializableClosure;
use Sassnowski\Venture\Collection\JobDefinitionCollection;
use Sassnowski\Venture\Workflow\JobDefinition;
use Sassnowski\Venture\Workflow\WorkflowStepInterface;
use Throwable;

/**
 * @method Workflow create(array $attributes)
 *
 * @property ?Carbon            $cancelled_at
 * @property ?string            $catch_callback
 * @property ?Carbon            $finished_at
 * @property array              $finished_jobs
 * @property int                $id
 * @property int                $job_count
 * @property EloquentCollection $jobs
 * @property int                $jobs_failed
 * @property int                $jobs_processed
 * @property string             $name
 * @property ?string            $then_callback
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Workflow extends Model
{
    protected $guarded = [];
    protected $casts = [
        'finished_jobs' => 'json',
        'job_count' => 'int',
        'jobs_failed' => 'int',
        'jobs_processed' => 'int',
    ];
    protected $dates = [
        'finished_at',
        'cancelled_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = config('venture.workflow_table');
        parent::__construct($attributes);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(WorkflowJob::class);
    }

    public function addJobs(JobDefinitionCollection $jobs): void
    {
        collect($jobs)->map(fn (JobDefinition $jobDefinition) => [
            'job' => \serialize($jobDefinition->job),
            'name' => $jobDefinition->name,
            'uuid' => $jobDefinition->job->getStepId(),
            'edges' => $jobDefinition->job->getDependantJobs(),
        ])
            ->pipe(function (Collection $jobs): void {
                $this->jobs()->createMany($jobs);
            });
    }

    public function onStepFinished(WorkflowStepInterface $job): void
    {
        $this->markJobAsFinished($job);

        if ($this->isCancelled()) {
            return;
        }

        if ($this->isFinished()) {
            $this->update(['finished_at' => Carbon::now()]);
            $this->runThenCallback();

            return;
        }

        if (empty($job->getDependantJobs())) {
            return;
        }

        $this->runDependantJobs($job);
    }

    public function onStepFailed(WorkflowStepInterface $job, Throwable $e): void
    {
        DB::transaction(function () use ($job, $e): void {
            /** @var self $workflow */
            $workflow = $this->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->getKey(), ['jobs_failed']);

            $this->jobs_failed = $workflow->jobs_failed + 1;
            $this->save();

            optional($job->step())->update([
                'failed_at' => now(),
                'exception' => (string) $e,
            ]);
        });

        $this->runCallback($this->catch_callback, $this, $job, $e);
    }

    public function isFinished(): bool
    {
        return $this->job_count === $this->jobs_processed;
    }

    public function isCancelled(): bool
    {
        return null !== $this->cancelled_at;
    }

    public function cancel(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->update(['cancelled_at' => now()]);
    }

    public function remainingJobs(): int
    {
        return $this->job_count - $this->jobs_processed;
    }

    public function failedJobs(): EloquentCollection
    {
        return $this->jobs()
            ->whereNotNull('failed_at')
            ->get();
    }

    public function pendingJobs(): EloquentCollection
    {
        return $this->jobs()
            ->whereNull('finished_at')
            ->whereNull('failed_at')
            ->get();
    }

    public function finishedJobs(): EloquentCollection
    {
        return $this->jobs()
            ->whereNotNull('finished_at')
            ->get();
    }

    public function asAdjacencyList(): array
    {
        return $this->jobs->mapWithKeys(fn (WorkflowJob $job) => [
            $job->uuid => [
                'name' => $job->name,
                'failed_at' => $job->failed_at,
                'finished_at' => $job->finished_at,
                'exception' => $job->exception,
                'edges' => $job->edges ?: [],
            ],
        ])->all();
    }

    private function markJobAsFinished(WorkflowStepInterface $job): void
    {
        DB::transaction(function () use ($job): void {
            /** @var self $workflow */
            $workflow = $this->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->getKey(), ['finished_jobs', 'jobs_processed']);

            $this->finished_jobs = \array_merge($workflow->finished_jobs, [$job->getStepId()]);
            $this->jobs_processed = $workflow->jobs_processed + 1;
            $this->save();

            optional($job->step())->update(['finished_at' => now()]);
        });
    }

    private function canJobRun(WorkflowStepInterface $job): bool
    {
        return collect($job->getDependencies())->every(function (string $dependency) {
            return \in_array($dependency, $this->finished_jobs, true);
        });
    }

    private function dispatchJob(WorkflowStepInterface $job): void
    {
        Container::getInstance()->get(Dispatcher::class)->dispatch($job);
    }

    private function runThenCallback(): void
    {
        $this->runCallback($this->then_callback, $this);
    }

    private function runCallback(?string $serializedCallback, mixed ...$args): void
    {
        if (null === $serializedCallback) {
            return;
        }

        $callback = \unserialize($serializedCallback);

        if ($callback instanceof SerializableClosure) {
            $callback = $callback->getClosure();
        }

        $callback(...$args);
    }

    private function runDependantJobs(WorkflowStepInterface $job): void
    {
        WorkflowJob::whereIn('uuid', $job->getDependantJobs())
            ->get('job')
            ->pluck('job')
            ->map(fn (string $job) => \unserialize($job))
            ->filter(fn (WorkflowStepInterface $job) => $this->canJobRun($job))
            ->each(function (WorkflowStepInterface $job): void {
                $this->dispatchJob($job);
            });
    }
}
