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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Stubs\DeleteModelJob;
use Stubs\TestModel;

uses(TestCase::class);

it('allows non-workflow jobs to delete serialized models', function (): void {
    Schema::create('test_models', function (Blueprint $table): void {
        $table->id();
        $table->timestamps();
    });

    $model = TestModel::create();

    Bus::dispatch((new DeleteModelJob($model))->onConnection('sync'));

    expect(TestModel::find($model->id))->toBeNull();
});
