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

namespace Sassnowski\Venture\Facades;

use Illuminate\Support\Facades\Facade;
use Sassnowski\Venture\Manager\WorkflowManagerFake;
use Sassnowski\Venture\WorkflowDefinition;

/**
 * @method static void assertNotStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static void assertStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static WorkflowDefinition define(string $workflowName)
 * @method static bool hasStarted(string $workflowClass, ?callable $callback = null)
 * @method static WorkflowDefinition startWorkflow(\Sassnowski\Venture\AbstractWorkflow $abstractWorkflow)
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
