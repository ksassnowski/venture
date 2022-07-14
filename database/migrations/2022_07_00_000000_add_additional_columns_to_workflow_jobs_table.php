<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('venture.jobs_table'), function (Blueprint $table): void {
            $table->timestamp('gated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->boolean('manual')->default(false);
        });
    }
};