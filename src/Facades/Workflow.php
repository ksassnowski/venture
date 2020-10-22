<?php declare(strict_types=1);

namespace Sassnowski\Venture\Facades;

use Illuminate\Support\Facades\Facade;
use Sassnowski\Venture\Manager\WorkflowManagerFake;

/**
 * @method static void assertStarted(string $workflowDefinition, ?callable $callback)
 */
class Workflow extends Facade
{
    public static function fake(): WorkflowManagerFake
    {
        static::swap($fake = new WorkflowManagerFake());

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'venture.manager';
    }
}
