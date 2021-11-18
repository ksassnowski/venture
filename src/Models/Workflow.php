<?php declare(strict_types=1);

namespace Sassnowski\Venture\Models;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Opis\Closure\SerializableClosure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
 * @property EloquentCollection $jobs
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

    public function addJobs(array $jobs): void
    {
        collect($jobs)->map(fn (array $job) => [
            'job' => serialize(clone $job['job']),
            'name' => $job['name'],
            'uuid' => $job['job']->stepId,
            'edges' => $job['job']->dependantJobs
        ])
        ->pipe(function (Collection $jobs) {
            $this->jobs()->createMany($jobs);
        });
    }

    public function onStepFinished(object $job): void
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

        if (empty($job->dependantJobs)) {
            return;
        }

        $this->runDependantJobs($job);
    }

    public function onStepFailed(object $job, Throwable $e): void
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

    public function hasRan(): bool
    {
        return ($this->jobs_processed + $this->jobs_failed) === $this->job_count;
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

    private function markJobAsFinished(object $job): void
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

    private function canJobRun(object $job): bool
    {
        return collect($job->dependencies)->every(function (string $dependency) {
            return in_array($dependency, $this->finished_jobs, true);
        });
    }

    private function dispatchJob(object $job): void
    {
        Container::getInstance()->get(Dispatcher::class)->dispatch($job);
    }

    private function runThenCallback(): void
    {
        $this->runCallback($this->then_callback, $this);
    }

    private function runCallback(?string $serializedCallback, mixed ...$args): void
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

    private function runDependantJobs(object $job): void
    {
        // @TODO: Should be removed in the next major version.
        //
        // This is to keep backwards compatibility for workflows
        // that were created when Venture still stored serialized
        // instances of a job's dependencies instead of the step id.
        if (is_object($job->dependantJobs[0])) {
            $dependantJobs = collect($job->dependantJobs);
        } else {
            $dependantJobs = WorkflowJob::whereIn('uuid', $job->dependantJobs)
                ->get('job')
                ->pluck('job')
                ->map(fn (string $job) => unserialize($job));
        }

        $dependantJobs
            ->filter(fn (object $job) => $this->canJobRun($job))
            ->each(function (object $job) {
                $this->dispatchJob($job);
            });
    }
}
