<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Types;

use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\Workflow;
use Sassnowski\Venture\WorkflowDefinition;
use Stubs\CustomWorkflowModel;
use Stubs\TestJob1;
use function PHPStan\Testing\assertType;

/**
 * @extends AbstractWorkflow<CustomWorkflowModel>
 */
final class CustomModelWorkflow extends AbstractWorkflow
{
    /**
     * @return WorkflowDefinition<CustomWorkflowModel>
     */
    public function definition(): WorkflowDefinition
    {
        $definition = $this->define('custom')
            ->addJob(new TestJob1())
            ->then(function (CustomWorkflowModel $workflow): void {
            })
            ->catch(function (CustomWorkflowModel $workflow): void {
            });

        assertType('Sassnowski\Venture\WorkflowDefinition<Stubs\CustomWorkflowModel>', $definition);

        return $definition;
    }
}

final class DefaultModelWorkflow extends AbstractWorkflow
{
    public function definition(): WorkflowDefinition
    {
        $definition = $this->define('default')
            ->then(function (Workflow $workflow): void {
            });

        assertType('Sassnowski\Venture\WorkflowDefinition<Sassnowski\Venture\Models\Workflow>', $definition);

        return $definition;
    }
}
