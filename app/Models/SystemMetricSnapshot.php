<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemMetricSnapshot extends Model
{
    protected $fillable = [
        'sampled_at',
        'php_memory_mb',
        'php_memory_percent',
        'php_peak_memory_mb',
        'server_memory_total_mb',
        'server_memory_free_mb',
        'server_memory_used_percent',
        'server_memory_source',
        'server_cpu_percent',
        'server_cpu_source',
        'load_1m',
        'load_5m',
        'load_15m',
        'cpu_cores',
        'db_ms',
        'db_ok',
        'redis_ms',
        'redis_ok',
        'cache_store',
        'queue_connection',
        'storage_free_gb',
        'storage_used_percent',
        'requests_per_minute',
        'frontend_requests_per_minute',
        'admin_requests_per_minute',
        'request_ms',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
            'php_memory_mb' => 'float',
            'php_memory_percent' => 'float',
            'php_peak_memory_mb' => 'float',
            'server_memory_total_mb' => 'integer',
            'server_memory_free_mb' => 'integer',
            'server_memory_used_percent' => 'float',
            'server_cpu_percent' => 'float',
            'load_1m' => 'float',
            'load_5m' => 'float',
            'load_15m' => 'float',
            'cpu_cores' => 'integer',
            'db_ms' => 'float',
            'db_ok' => 'boolean',
            'redis_ms' => 'float',
            'redis_ok' => 'boolean',
            'storage_free_gb' => 'float',
            'storage_used_percent' => 'float',
            'requests_per_minute' => 'integer',
            'frontend_requests_per_minute' => 'integer',
            'admin_requests_per_minute' => 'integer',
            'request_ms' => 'float',
        ];
    }
}
