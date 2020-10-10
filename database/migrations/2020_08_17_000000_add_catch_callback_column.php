<?php declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCatchCallbackColumn extends Migration
{
    public function up()
    {
        Schema::table(config('venture.workflow_table'), function (Blueprint $table) {
            $table->text('catch_callback')->nullable();
        });
    }
}
