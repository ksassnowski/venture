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

use Sassnowski\Venture\Plugin\EntityAwareWorkflows;
use Sassnowski\Venture\Venture;
use Stubs\EntityAwareTestWorkflow;
use Stubs\TestModel;

uses(TestCase::class);

it('does not store an associated entity if the plugin is not registered', function (): void {
    $entity = new TestModel(['id' => 5]);
    $workflow = new EntityAwareTestWorkflow($entity);

    [$model, $initialJobs] = $workflow->getDefinition()->build();

    expect($model)
        ->workflowable_type->toBeNull()
        ->workflowable_id->toBeNull();
});

it('stores the associated entity in the database if the plugin was registered', function (): void {
    Venture::registerPlugin(EntityAwareWorkflows::class);
    Venture::bootPlugins();

    $entity = new TestModel(['id' => 5]);
    $workflow = new EntityAwareTestWorkflow($entity);

    [$model, $initialJobs] = $workflow->getDefinition()->build();

    expect($model)
        ->workflowable_type->toBe(TestModel::class)
        ->workflowable_id->toBe(5);
});
