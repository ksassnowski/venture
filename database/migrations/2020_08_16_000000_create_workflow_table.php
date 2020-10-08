<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkflowTable extends Migration
{
    public function up()
    {
        Schema::create(config('workflow.workflow_table'), function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('job_count');
            $table->integer('jobs_processed');
            $table->integer('jobs_failed');
            $table->json('finished_jobs');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create(config('workflow.jobs_table'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('name');
            $table->text('job');
            $table->unsignedBigInteger('workflow_id');
            $table->timestamp('finished_at')->nullable();

            $table->foreign('workflow_id')->references('id')->on(config('workflow.workflow_table'))
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }
}
