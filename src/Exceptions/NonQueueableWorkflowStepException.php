<?php declare(strict_types=1);

namespace Sassnowski\Venture\Exceptions;

use Exception;

class NonQueueableWorkflowStepException extends Exception
{
    public static function fromJob($job): NonQueueableWorkflowStepException
    {
        return new self(sprintf(
            "Job [%s] does not implement the 'ShouldQueue' interface. If you want to " .
            'execute the job synchronously, you should explicitly dispatch it on the `sync` connection.',
            get_class($job)
        ));
    }
}
