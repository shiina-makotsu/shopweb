<?php

return [
    'installer_enabled' => (bool) env('SHOP_INSTALLER_ENABLED', false),
    'auto_migrate_on_boot' => (bool) env('SHOP_AUTO_MIGRATE_ON_BOOT', true),
    'auto_migrate_check_ttl' => (int) env('SHOP_AUTO_MIGRATE_CHECK_TTL', 60),
];
