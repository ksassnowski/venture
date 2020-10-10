<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdditionalColumnsToWorkflow extends Migration
{
    public function up()
    {
        Schema::table(config('venture.workflow_table'), function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable();
            $table->text('catch_callback')->nullable();
        });

        Schema::table(config('venture.jobs_table'), function (Blueprint $table) {
            $table->timestamp('failed_at')->nullable();
        });
    }
}
