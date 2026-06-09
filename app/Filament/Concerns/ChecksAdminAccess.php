<?php

namespace App\Filament\Concerns;

use App\Support\AdminAccess;

trait ChecksAdminAccess
{
    public static function canAccess(): bool
    {
        return AdminAccess::can(static::$permissionArea ?? 'admin');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }
}
