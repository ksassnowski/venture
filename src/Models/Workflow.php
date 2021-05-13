<?php declare(strict_types=1);

namespace Sassnowski\Venture\Models;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Opis\Closure\SerializableClosure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method Workflow create(array $attributes)
 * @property int $id
 * @property string $name
 * @property array $finished_jobs
 * @property int $jobs_processed
 * @property int $jobs_failed
 * @property int $job_count
 * @property ?string $then_callback
 * @property ?string $catch_callback
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $finished_at
 * @property Collection $jobs
 */
class Workflow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'finished_jobs' => 'json',
        'job_count' => 'int',
        'jobs_processed' => 'int',
    ];

    protected $dates = [
        'finished_at',
        'cancelled_at',
    ];

    public function __construct($attributes = [])
    {
        $this->table = config('venture.workflow_table');
        parent::__construct($attributes);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(WorkflowJob::class);
    }

    public function addJobs(array $jobs): void
    {
        collect($jobs)->map(fn ($job) => [
            'job' => serialize($job['job']),
            'name' => $job['name'],
            'uuid' => $job['job']->stepId,
            'edges' => $job['job']->dependantJobs
        ])
        ->pipe(function ($jobs) {
            $this->jobs()->createMany($jobs);
        });
    }

    public function onStepFinished($job): void
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

        WorkflowJob::whereIn('uuid', $job->dependantJobs)
            ->get('job')
            ->pluck('job')
            ->map(fn ($job) => unserialize($job))
            ->filter(fn ($job) => $this->canJobRun($job))
            ->each(function ($job) {
                $this->dispatchJob($job);
            });
    }

    public function onStepFailed($job, Throwable $e): void
    {
        DB::transaction(function () use ($job, $e) {
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
        return $this->cancelled_at !== null;
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

    public function failedJobs(): Collection
    {
        return $this->jobs()
            ->whereNotNull('failed_at')
            ->get();
    }

    public function pendingJobs(): Collection
    {
        return $this->jobs()
            ->whereNull('finished_at')
            ->whereNull('failed_at')
            ->get();
    }

    public function finishedJobs(): Collection
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

    private function markJobAsFinished($job): void
    {
        DB::transaction(function () use ($job) {
            /** @var self $workflow */
            $workflow = $this->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->getKey(), ['finished_jobs', 'jobs_processed']);

            $this->finished_jobs = array_merge($workflow->finished_jobs, [$job->jobId ?: get_class($job)]);
            $this->jobs_processed = $workflow->jobs_processed + 1;
            $this->save();

            optional($job->step())->update(['finished_at' => now()]);
        });
    }

    private function canJobRun($job): bool
    {
        return collect($job->dependencies)->every(function (string $dependency) {
            return in_array($dependency, $this->finished_jobs, true);
        });
    }

    private function dispatchJob($job): void
    {
        Container::getInstance()->get(Dispatcher::class)->dispatch($job);
    }

    private function runThenCallback(): void
    {
        $this->runCallback($this->then_callback, $this);
    }

    private function runCallback(?string $serializedCallback, ...$args): void
    {
        if ($serializedCallback === null) {
            return;
        }

        $callback = unserialize($serializedCallback);

        if ($callback instanceof SerializableClosure) {
            $callback = $callback->getClosure();
        }

        $callback(...$args);
    }
}
