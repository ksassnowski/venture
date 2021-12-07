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

namespace Sassnowski\Venture\Exceptions;

use Exception;
use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\ProvidesSolution;
use Facade\IgnitionContracts\Solution;
use Illuminate\Contracts\Queue\ShouldQueue;

class NonQueueableWorkflowStepException extends Exception implements ProvidesSolution
{
    public static function fromJob(object $job): self
    {
        return new self(\sprintf("Job [%s] does not implement the 'ShouldQueue' interface", \get_class($job)));
    }

    public function getSolution(): Solution
    {
        return BaseSolution::create('Non-queueable jobs')
            ->setSolutionDescription(\sprintf(
                'All jobs used in a workflow need to provide the [%s] interface. If you want to execute ' .
                'execute jobs synchronously, you should explicitly dispatch them on the `sync` connection.',
                ShouldQueue::class,
            ))
            ->setDocumentationLinks([
                'Laravel documentation' => 'https://laravel.com/docs/8.x/queues#synchronous-dispatching',
            ]);
    }
}
