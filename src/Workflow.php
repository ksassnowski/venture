<?php declare(strict_types=1);

namespace Sassnowski\LaravelWorkflow;

use Sassnowski\LaravelWorkflow\Graph\DependencyGraph;

class Workflow
{
    private array $initialBatch;
    private DependencyGraph $graph;
    private array $finishedJobs = [];

    public function __construct(array $initialBatch, DependencyGraph $graph)
    {
        $this->initialBatch = $initialBatch;
        $this->graph = $graph;
    }

    public static function withInitialJobs(array $initialJobs)
    {
        return new PendingWorkflow($initialJobs);
    }

    public function start(): void
    {
        // Create record in database to track state

        // Dispatch initial batch
        collect($this->initialBatch)->each(function ($job) {
            echo 'dispatching job ' . $job . PHP_EOL;
            $this->onStepFinished($job);
        });
    }

    public function onStepFinished(string $job)
    {
        $this->markJobAsFinished($job);

        collect($this->graph->getDependants($job))
            ->filter(function (string $job) {
                return $this->canJobRun($job);
            })
            ->each(function (string $job) {
                echo 'dispatching job ' . $job . PHP_EOL;
                $this->onStepFinished($job);
            });
    }

    private function markJobAsFinished(string $job): void
    {
        $this->finishedJobs[] = $job;
    }

    private function canJobRun(string $job): bool
    {
        return collect($this->graph->getDependencies($job))->every(function (string $dependency) {
            return in_array($dependency, $this->finishedJobs);
        });
    }
}
