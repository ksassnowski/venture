<?php declare(strict_types=1);

namespace Sassnowski\Venture\Facades;

use Illuminate\Support\Facades\Facade;
use Sassnowski\Venture\WorkflowDefinition;
use Sassnowski\Venture\Manager\WorkflowManagerFake;

/**
 * @method static WorkflowDefinition define(string $workflowName)
 * @method static void assertStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static void assertNotStarted(string $workflowDefinition, ?callable $callback = null)
 */
class Workflow extends Facade
{
    public static function fake(): WorkflowManagerFake
    {
        static::swap($fake = new WorkflowManagerFake(static::getFacadeRoot()));

        return $fake;
    }

    protected static function getFacadeAccessor()
    {
        return 'venture.manager';
    }
}
