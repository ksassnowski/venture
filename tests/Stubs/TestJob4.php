<?php declare(strict_types=1);

namespace Stubs;

use Illuminate\Bus\Queueable;
use Sassnowski\Venture\WorkflowStep;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestJob4 implements ShouldQueue
{
    use Queueable, WorkflowStep;
}
