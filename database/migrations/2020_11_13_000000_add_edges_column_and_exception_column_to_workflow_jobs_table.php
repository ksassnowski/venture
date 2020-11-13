<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEdgesColumnAndExceptionColumnToWorkflowJobsTable extends Migration
{
    public function up(): void
    {
        Schema::table(config('venture.jobs_table'), function (Blueprint $table) {
            $table->json('edges')->nullable();
            $table->longText('exception')->nullable();
        });
    }
}
