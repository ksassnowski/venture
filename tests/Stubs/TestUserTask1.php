<?php declare(strict_types=1);

namespace Stubs;

use Illuminate\Bus\Queueable;
use Sassnowski\Venture\WorkflowStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sassnowski\Venture\WorkflowUserTask;

class TestUserTask1 implements ShouldQueue
{
    use Queueable, WorkflowStep;

    public function isUserTask(): bool
    {
        return true;
    }
}
