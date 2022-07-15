<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('venture.jobs_table'), function (Blueprint $table): void {
            $table->after('finished_at', function (Blueprint $t): void {
                $t->timestamp('gated_at')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->boolean('gated')->default(false);
            });
        });
    }
};