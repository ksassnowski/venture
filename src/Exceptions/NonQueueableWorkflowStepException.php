<?php declare(strict_types=1);

namespace Sassnowski\Venture\Exceptions;

use Exception;
use Facade\IgnitionContracts\Solution;
use Facade\IgnitionContracts\BaseSolution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Facade\IgnitionContracts\ProvidesSolution;

class NonQueueableWorkflowStepException extends Exception implements ProvidesSolution
{
    public static function fromJob($job): NonQueueableWorkflowStepException
    {
        return new self(sprintf("Job [%s] does not implement the 'ShouldQueue' interface. If you want to " . get_class($job)));
    }

    public function getSolution(): Solution
    {
        return BaseSolution::create('Non-queueable jobs')
            ->setSolutionDescription(sprintf(
                'All jobs used in a workflow need to provide the [%s] interface. If you want to execute ' .
                'execute jobs synchronously, you should explicitly dispatch them on the `sync` connection.',
                [ShouldQueue::class]
            ))
            ->setDocumentationLinks([
                'Laravel documentation' => 'https://laravel.com/docs/8.x/queues#synchronous-dispatching',
            ]);
    }
}
