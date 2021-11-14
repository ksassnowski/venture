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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowTable extends Migration
{
    public function up(): void
    {
        Schema::create(config('venture.workflow_table'), function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('job_count');
            $table->integer('jobs_processed');
            $table->integer('jobs_failed');
            $table->json('finished_jobs');
            $table->text('then_callback')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create(config('venture.jobs_table'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->string('name');
            $table->text('job');
            $table->unsignedBigInteger('workflow_id');
            $table->timestamp('finished_at')->nullable();

            $table->foreign('workflow_id')->references('id')->on(config('venture.workflow_table'))
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }
}
