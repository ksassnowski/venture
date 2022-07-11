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
use Sassnowski\Venture\Models\Workflow;

abstract class AbstractWorkflow
{
    public static function start(): Workflow
    {
        /** @psalm-suppress TooManyArguments, UnsafeInstantiation */
        return (new static(...\func_get_args()))->run();
    }

    abstract public function definition(): WorkflowDefinition;

    public function beforeCreate(Workflow $workflow): void
    {
    }

    public function beforeNesting(array $jobs): void
    {
    }

    protected function define(string $workflowName = ''): WorkflowDefinition
    {
        return new WorkflowDefinition($this, $workflowName);
    }

    private function run(): Workflow
    {
        return Container::getInstance()
            ->make('venture.manager')
            ->startWorkflow($this);
    }
}
