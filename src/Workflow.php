<?php declare(strict_types=1);

namespace Sassnowski\Venture;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Opis\Closure\SerializableClosure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method Workflow create(array $attributes)
 * @property string $id
 * @property string $name
 * @property array $finished_jobs
 * @property int $jobs_processed
 * @property int $job_count
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
        $this->table = config('workflows.workflow_table');
        parent::__construct($attributes);
    }

    public static function new(string $workflowName)
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

        if ($this->isFinished()) {
            $this->update(['finished_at' => Carbon::now()]);
            $this->runThenCallback();
            return;
        }

        collect($job->dependantJobs)
            ->filter(function ($job) {
                return $this->canJobRun($job);
            })
            ->each(function ($job) {
                $this->dispatchJob($job);
            });
    }

    public function isFinished(): bool
    {
        return $this->job_count === $this->jobs_processed;
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
        if (($serializedCallback = $this->then_callback) === null) {
            return;
        }

        $callback = unserialize($serializedCallback);

        if ($callback instanceof SerializableClosure) {
            $callback = $callback->getClosure();
        }

        call_user_func($callback, $this);
    }
}
