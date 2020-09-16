<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWorkflowTable extends Migration
{
    public function up()
    {
        Schema::create(config('workflow.workflow_table'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('job_count');
            $table->integer('jobs_processed');
            $table->integer('jobs_failed');
            $table->json('state');
        });
    }
}
