<?php

declare(strict_types=1);

/**
 * Copyright (c) 2022 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ksassnowski/venture
 */

namespace Stubs;

use Illuminate\Database\Eloquent\Model;
use Sassnowski\Venture\AbstractWorkflow;
use Sassnowski\Venture\Models\EntityAwareWorkflowInterface;
use Sassnowski\Venture\WorkflowDefinition;

final class EntityAwareTestWorkflow extends AbstractWorkflow implements EntityAwareWorkflowInterface
{
    public function __construct(private Model $entity)
    {
    }

    public function definition(): WorkflowDefinition
    {
        return $this->define()
            ->addJob(new TestJob1());
    }

    public function getWorkflowable(): Model
    {
        return $this->entity;
    }
}
