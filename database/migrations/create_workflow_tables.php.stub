<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration {
    public function up()
    {
        Schema::create(config('venture.workflow_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('job_count');
            $table->integer('jobs_processed');
            $table->integer('jobs_failed');
            $table->json('finished_jobs');
            $table->nullableMorphs('workflowable');
            $table->text('then_callback')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('catch_callback')->nullable();
            $table->timestamps();
        });

        Schema::create(config('venture.jobs_table'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->index();
            $table->string('name');
            $table->text('job');
            $table->unsignedBigInteger('workflow_id');
            $table->json('edges')->nullable();
            $table->longText('exception')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('gated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->boolean('gated')->default(false);

            $table->foreign('workflow_id')
                ->references('id')
                ->on(config('venture.workflow_table'))
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};
