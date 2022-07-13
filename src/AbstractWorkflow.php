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

namespace Sassnowski\Venture;

use Illuminate\Container\Container;
use Sassnowski\Venture\Manager\WorkflowManagerInterface;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\Testing\WorkflowTester;

abstract class AbstractWorkflow
{
    public static function start(): Workflow
    {
        /** @phpstan-ignore-next-line */
        return (new static(...\func_get_args()))->run();
    }

    public static function startOnConnection(string $connection, mixed ...$args): Workflow
    {
        /** @phpstan-ignore-next-line */
        return (new static(...$args))->run($connection);
    }

    public static function startSync(mixed ...$args): Workflow
    {
        /** @phpstan-ignore-next-line */
        return (new static(...$args))->run('sync');
    }

    public static function test(mixed ...$arguments): WorkflowTester
    {
        /** @phpstan-ignore-next-line */
        return new WorkflowTester(new static(...$arguments));
    }

    abstract public function definition(): WorkflowDefinition;

    public function beforeCreate(Workflow $workflow): void
    {
    }

    /**
     * @param array<string, WorkflowStepInterface> $jobs
     */
    public function beforeNesting(array $jobs): void
    {
    }

    protected function define(string $workflowName = ''): WorkflowDefinition
    {
        return new WorkflowDefinition($this, $workflowName);
    }

    private function run(?string $connection = null): Workflow
    {
        /** @var WorkflowManagerInterface $manager */
        $manager = Container::getInstance()->make('venture.manager');

        return $manager->startWorkflow($this, $connection);
    }
}
