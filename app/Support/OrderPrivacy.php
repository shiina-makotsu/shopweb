<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;

class OrderPrivacy
{
    public function canViewOrderNumber(?User $user, ?SiteSetting $settings = null): bool
    {
        if ($user) {
            return (bool) $user->can_view_order_numbers;
        }

        return (bool) ($settings ?? SiteSetting::query()->first())?->show_order_numbers_to_users;
    }

    public function canViewTrackingNumber(?User $user, ?SiteSetting $settings = null): bool
    {
        return $this->canViewTrackingNumberForOrder(null, $user, $settings);
    }

    public function canViewTrackingNumberForOrder(?Order $order, ?User $user, ?SiteSetting $settings = null): bool
    {
        $order?->loadMissing('shippingCarrier');

        if ($order?->shippingCarrier?->is_international) {
            return (bool) $user?->can_view_tracking_numbers;
        }

        if ($user?->can_view_tracking_numbers) {
            return true;
        }

        return (bool) (($settings ?? SiteSetting::query()->first())?->show_tracking_numbers_to_users ?? true);
    }

    public function displayOrderNumber(Order $order, ?User $user, ?SiteSetting $settings = null): string
    {
        if ($this->canViewOrderNumber($user, $settings)) {
            return $order->order_number;
        }

        return '订单 #'.$order->id;
    }

    public function displayTrackingNumber(Order $order, ?User $user, ?SiteSetting $settings = null): string
    {
        if (! $order->tracking_number) {
            return '-';
        }

        if ($this->canViewTrackingNumberForOrder($order, $user, $settings)) {
            return $order->tracking_number;
        }

        return '后台已隐藏';
    }
}
