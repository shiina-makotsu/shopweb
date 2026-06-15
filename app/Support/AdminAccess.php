<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminAccess
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_FINANCE = 'finance';
    public const ROLE_WAREHOUSE = 'warehouse';
    public const ROLE_SALES = 'sales';
    public const ROLE_PURCHASING = 'purchasing';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_CUSTOMER = 'customer';

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => '超级管理员',
            self::ROLE_OPERATOR => '运营',
            self::ROLE_FINANCE => '财务',
            self::ROLE_WAREHOUSE => '仓库',
            self::ROLE_SALES => '销售',
            self::ROLE_PURCHASING => '采购',
            self::ROLE_SUPPORT => '客服',
            self::ROLE_CUSTOMER => '前台用户',
        ];
    }

    /**
     * @return array<string>
     */
    public static function panelRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_OPERATOR,
            self::ROLE_FINANCE,
            self::ROLE_WAREHOUSE,
            self::ROLE_SALES,
            self::ROLE_PURCHASING,
            self::ROLE_SUPPORT,
        ];
    }

    public static function canAccessPanel(?User $user): bool
    {
        return $user && in_array($user->role, self::panelRoles(), true);
    }

    public static function can(string $area, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->role === self::ROLE_ADMIN) {
            return true;
        }

        return in_array($area, self::allowedAreas($user->role), true);
    }

    public static function canAction(string $action, ?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->role === self::ROLE_ADMIN) {
            return true;
        }

        return in_array($user->role, self::actionRoles($action), true);
    }

    /**
     * @return array<string>
     */
    public static function actionRoles(string $action): array
    {
        return match ($action) {
            'orders.confirm_payment', 'orders.reject_payment' => [
                self::ROLE_FINANCE,
            ],
            'inventory.adjust' => [
                self::ROLE_WAREHOUSE,
            ],
            'procurement.receive' => [
                self::ROLE_PURCHASING,
                self::ROLE_WAREHOUSE,
            ],
            'finance.manage_costs' => [
                self::ROLE_FINANCE,
            ],
            'after_sales.request_refund' => [
                self::ROLE_SUPPORT,
            ],
            'after_sales.refund' => [
                self::ROLE_FINANCE,
                self::ROLE_SALES,
            ],
            'coupons.issue_request' => [
                self::ROLE_SUPPORT,
            ],
            'coupons.issue' => [
                self::ROLE_OPERATOR,
                self::ROLE_FINANCE,
                self::ROLE_SALES,
            ],
            'approvals.review' => [
                self::ROLE_OPERATOR,
                self::ROLE_FINANCE,
                self::ROLE_SALES,
            ],
            'after_sales.resolve' => [
                self::ROLE_SUPPORT,
                self::ROLE_FINANCE,
                self::ROLE_SALES,
            ],
            default => [],
        };
    }

    /**
     * @return array<string>
     */
    public static function allowedAreas(string $role): array
    {
        return match ($role) {
            self::ROLE_OPERATOR => [
                'approvals',
                'catalog',
                'orders',
                'customers',
                'content',
                'media',
                'resources',
                'forum',
                'procurement',
                'finance',
                'coupons',
                'reports',
            ],
            self::ROLE_FINANCE => [
                'approvals',
                'orders',
                'payments',
                'coupons',
                'customers',
                'reports',
                'activity',
                'finance',
            ],
            self::ROLE_WAREHOUSE => [
                'catalog',
                'inventory',
                'reports',
                'procurement',
            ],
            self::ROLE_SALES => [
                'approvals',
                'orders',
                'customers',
                'coupons',
                'reports',
                'finance',
            ],
            self::ROLE_PURCHASING => [
                'catalog',
                'inventory',
                'media',
                'resources',
                'procurement',
                'reports',
            ],
            self::ROLE_SUPPORT => [
                'customers',
                'content',
                'forum',
                'support',
                'media',
                'resources',
                'activity',
            ],
            default => [],
        };
    }
}
