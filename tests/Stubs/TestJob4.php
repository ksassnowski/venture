<?php declare(strict_types=1);

namespace Stubs;

use Illuminate\Bus\Queueable;
use Sassnowski\Venture\WorkflowStep;

class TestJob4
{
    use Queueable, WorkflowStep;
}
