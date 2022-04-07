<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkflowIdColumnToWorkflowsTable extends Migration
{
    public function up()
    {
        Schema::table(config('venture.workflow_table'), function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_id')->nullable();
        });
    }
}
