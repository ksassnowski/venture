<?php declare(strict_types=1);

namespace Sassnowski\Venture\Facades;

use Illuminate\Support\Facades\Facade;
use Sassnowski\Venture\WorkflowDefinition;
use Sassnowski\Venture\Manager\WorkflowManagerFake;

/**
 * @method static WorkflowDefinition define(string $workflowName)
 * @method static WorkflowDefinition startWorkflow(\Sassnowski\Venture\AbstractWorkflow $abstractWorkflow)
 * @method static bool hasStarted(string $workflowClass, ?callable $callback = null)
 * @method static void assertStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static void assertNotStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static void completeJob(int $jobId)
 *
 * @see \Sassnowski\Venture\Manager\WorkflowManager
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
