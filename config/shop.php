<?php

return [
    'installer_enabled' => (bool) env('SHOP_INSTALLER_ENABLED', false),
    'auto_migrate_on_boot' => (bool) env('SHOP_AUTO_MIGRATE_ON_BOOT', true),
    'auto_migrate_check_ttl' => (int) env('SHOP_AUTO_MIGRATE_CHECK_TTL', 60),
    'load_shedding' => [
        'enabled' => (bool) env('SHOP_LOAD_SHEDDING_ENABLED', true),
        'frontend_capacity' => (int) env('SHOP_TOKEN_BUCKET_FRONTEND_CAPACITY', 180),
        'frontend_refill_per_second' => (int) env('SHOP_TOKEN_BUCKET_FRONTEND_REFILL', 45),
        'admin_capacity' => (int) env('SHOP_TOKEN_BUCKET_ADMIN_CAPACITY', 90),
        'admin_refill_per_second' => (int) env('SHOP_TOKEN_BUCKET_ADMIN_REFILL', 20),
        'busy_recovery_minutes' => (int) env('SHOP_BUSY_RECOVERY_MINUTES', 10),
        'retry_base_seconds' => (int) env('SHOP_BUSY_RETRY_BASE_SECONDS', 3),
        'retry_max_seconds' => (int) env('SHOP_BUSY_RETRY_MAX_SECONDS', 192),
    ],
    'cache_prewarm' => [
        'enabled' => (bool) env('SHOP_CACHE_PREWARM_ENABLED', true),
        'include_admin' => (bool) env('SHOP_CACHE_PREWARM_INCLUDE_ADMIN', false),
        'urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('SHOP_CACHE_PREWARM_URLS', '/,/products,/forum,/ai-image'))))),
        'ttl_seconds' => (int) env('SHOP_CACHE_PREWARM_TTL', 600),
    ],
    'analytics' => [
        'track_admin_page_views' => (bool) env('SHOP_ANALYTICS_TRACK_ADMIN_PAGE_VIEWS', false),
    ],
    'first_visit_loading' => [
        'enabled' => (bool) env('SHOP_FIRST_VISIT_LOADING_ENABLED', env('APP_ENV') !== 'testing'),
        'show_on_cold_cache' => (bool) env('SHOP_FIRST_VISIT_LOADING_ON_COLD_CACHE', true),
    ],
    'alert_bot' => [
        'enabled' => (bool) env('SHOP_ALERT_BOT_ENABLED', false),
        'driver' => env('SHOP_ALERT_BOT_DRIVER', 'webhook'),
        'webhook_url' => env('SHOP_ALERT_BOT_WEBHOOK_URL'),
        'token' => env('SHOP_ALERT_BOT_TOKEN'),
        'target' => env('SHOP_ALERT_BOT_TARGET'),
        'timeout_seconds' => (int) env('SHOP_ALERT_BOT_TIMEOUT', 5),
        'cooldown_seconds' => (int) env('SHOP_ALERT_BOT_COOLDOWN', 300),
    ],
    'server_monitor' => [
        'enabled' => (bool) env('SHOP_SERVER_MONITOR_ENABLED', true),
        'cpu_percent_warning' => (float) env('SHOP_SERVER_CPU_WARNING', 90),
        'memory_percent_warning' => (float) env('SHOP_SERVER_MEMORY_WARNING', 90),
        'disk_percent_warning' => (float) env('SHOP_SERVER_DISK_WARNING', 90),
        'db_ms_warning' => (float) env('SHOP_SERVER_DB_MS_WARNING', 1500),
        'redis_ms_warning' => (float) env('SHOP_SERVER_REDIS_MS_WARNING', 1000),
        'request_ms_warning' => (float) env('SHOP_SERVER_REQUEST_MS_WARNING', 3000),
        'rpm_warning' => (int) env('SHOP_SERVER_RPM_WARNING', 600),
        'retention_days' => (int) env('SHOP_SERVER_METRIC_RETENTION_DAYS', 62),
    ],
    'redis_deployment' => [
        'recommended_topology' => env('SHOP_REDIS_TOPOLOGY', '3-master-3-replica-sentinel'),
        'maxmemory_policy' => env('SHOP_REDIS_MAXMEMORY_POLICY', 'allkeys-lfu'),
        'lazyfree_lazy_eviction' => (bool) env('SHOP_REDIS_LAZYFREE_LAZY_EVICTION', true),
        'lazyfree_lazy_expire' => (bool) env('SHOP_REDIS_LAZYFREE_LAZY_EXPIRE', true),
        'lazyfree_lazy_server_del' => (bool) env('SHOP_REDIS_LAZYFREE_LAZY_SERVER_DEL', true),
    ],
];
