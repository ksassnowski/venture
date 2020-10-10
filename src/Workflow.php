<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Opis\Closure\SerializableClosure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method Workflow create(array $attributes)
 * @property int $id
 * @property string $name
 * @property array $finished_jobs
 * @property int $jobs_processed
 * @property int $job_count
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $finished_at
 */
class Workflow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'finished_jobs' => 'json',
        'job_count' => 'int',
        'jobs_processed' => 'int',
    ];

    public function __construct($attributes = [])
    {
        $this->table = config('venture.workflow_table');
        parent::__construct($attributes);
    }

    public static function new(string $workflowName): PendingWorkflow
    {
        return new PendingWorkflow($workflowName);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(WorkflowJob::class);
    }

    public function start(array $initialBatch): void
    {
        collect($initialBatch)->each(function ($job) {
            $this->dispatchJob($job);
        });
    }

    public function addJobs(array $jobs): void
    {
        collect($jobs)->map(fn ($job) => [
            'job' => serialize($job['job']),
            'name' => $job['name'],
            'uuid' => $job['job']->stepId,
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

        collect($job->dependantJobs)
            ->filter(fn ($job) => $this->canJobRun($job))
            ->each(function ($job) {
                $this->dispatchJob($job);
            });
    }

    public function onStepFailed($job, Throwable $e)
    {
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
        $this->update(['cancelled_at' => now()]);
    }

    public function remainingJobs(): int
    {
        return $this->job_count - $this->jobs_processed;
    }

    private function markJobAsFinished($job): void
    {
        DB::transaction(function () use ($job) {
            $this->finished_jobs = array_merge($this->finished_jobs, [get_class($job)]);
            $this->jobs_processed++;
            $this->save();

            optional($job->step())->update(['finished_at' => now()]);
        });
    }

    private function canJobRun($job): bool
    {
        return collect($job->dependencies)->every(function (string $dependency) {
            return in_array($dependency, $this->finished_jobs);
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

        call_user_func($callback, ...$args);
    }
}
