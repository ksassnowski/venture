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
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Manager\WorkflowManagerFake;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;
use Sassnowski\Venture\WorkflowDefinition;

/**
 * @method static void assertNotStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static void assertStarted(string $workflowDefinition, ?callable $callback = null)
 * @method static WorkflowDefinition define(string $workflowName)
 * @method static bool hasStarted(string $workflowClass, ?callable $callback = null)
 * @method static WorkflowDefinition startWorkflow(AbstractWorkflow $abstractWorkflow)
 *
 * @see \Sassnowski\Venture\Manager\WorkflowManager
 */
class Workflow extends Facade
{
    public static function fake(): WorkflowManagerFake
    {
        /** @var WorkflowManagerInterface $manager */
        $manager = static::getFacadeRoot();

        static::swap($fake = new WorkflowManagerFake($manager));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'venture.manager';
    }
}
