<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_metric_snapshots')) {
            return;
        }

        Schema::create('system_metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('sampled_at')->unique();
            $table->decimal('php_memory_mb', 10, 2)->nullable();
            $table->decimal('php_memory_percent', 7, 2)->nullable();
            $table->decimal('php_peak_memory_mb', 10, 2)->nullable();
            $table->unsignedInteger('server_memory_total_mb')->nullable();
            $table->unsignedInteger('server_memory_free_mb')->nullable();
            $table->decimal('server_memory_used_percent', 7, 2)->nullable();
            $table->string('server_memory_source', 40)->nullable();
            $table->decimal('server_cpu_percent', 7, 2)->nullable();
            $table->string('server_cpu_source', 40)->nullable();
            $table->decimal('load_1m', 10, 2)->nullable();
            $table->decimal('load_5m', 10, 2)->nullable();
            $table->decimal('load_15m', 10, 2)->nullable();
            $table->unsignedSmallInteger('cpu_cores')->default(1);
            $table->decimal('db_ms', 10, 2)->nullable();
            $table->boolean('db_ok')->default(false);
            $table->decimal('redis_ms', 10, 2)->nullable();
            $table->boolean('redis_ok')->default(false);
            $table->string('cache_store', 60)->nullable();
            $table->string('queue_connection', 60)->nullable();
            $table->decimal('storage_free_gb', 12, 2)->nullable();
            $table->decimal('storage_used_percent', 7, 2)->nullable();
            $table->unsignedInteger('requests_per_minute')->default(0);
            $table->unsignedInteger('frontend_requests_per_minute')->default(0);
            $table->unsignedInteger('admin_requests_per_minute')->default(0);
            $table->decimal('request_ms', 10, 2)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_metric_snapshots');
    }
};
