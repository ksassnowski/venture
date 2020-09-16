<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;

class Workflow
{
    private string $id;
    private array $finishedJobs;
    private int $jobCount;
    private int $jobsProcessed;
    private int $jobsFailed;
    private WorkflowRepository $repository;

    public function __construct(
        string $id,
        WorkflowRepository $repository,
        array $finishedJobs = [],
        int $jobCount = 0,
        int $jobsProcessed = 0,
        int $jobsFailed = 0
    ) {
        $this->id = $id;
        $this->jobCount = $jobCount;
        $this->jobsProcessed = $jobsProcessed;
        $this->jobsFailed = $jobsFailed;
        $this->finishedJobs = $finishedJobs;
        $this->repository = $repository;
    }

    public static function withInitialJobs(array $initialJobs)
    {
        return new PendingWorkflow($initialJobs);
    }

    public function getId()
    {
        return $this->id;
    }

    public function start(array $initialBatch): void
    {
        collect($initialBatch)->each(function ($job) {
            $this->dispatchJob($job);
        });
    }

    public function onStepFinished($job)
    {
        $this->markJobAsFinished($job);

        $this->repository->updateValues($this->id, [
            'state' => $this->finishedJobs,
            'jobs_processed' => $this->jobsProcessed + 1,
        ]);

        collect($job->dependantJobs)
            ->filter(function ($job) {
                return $this->canJobRun($job);
            })
            ->each(function ($job) {
                $this->dispatchJob($job);
            });
    }

    private function markJobAsFinished($job): void
    {
        $this->finishedJobs[] = get_class($job);
    }

    private function canJobRun($job): bool
    {
        return collect($job->dependencies)->every(function (string $dependency) {
            return in_array($dependency, $this->finishedJobs);
        });
    }

    private function dispatchJob($job)
    {
        Container::getInstance()->get(Dispatcher::class)->dispatch($job);
    }
}
