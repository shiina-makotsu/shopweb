<?php

namespace App\Support;

use App\Models\OrderStatusSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderStatusPresenter
{
    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->settings('label', OrderStatusSetting::fallbackLabels());
    }

    public function label(?string $status): string
    {
        if (! $status) {
            return '-';
        }

        return $this->options()[$status] ?? $status;
    }

    public function color(?string $status): string
    {
        if (! $status) {
            return 'gray';
        }

        return $this->settings('color', OrderStatusSetting::fallbackColors())[$status] ?? 'gray';
    }

    /**
     * @param  array<string, string>  $fallback
     * @return array<string, string>
     */
    private function settings(string $field, array $fallback): array
    {
        try {
            if (! Schema::hasTable('order_status_settings')) {
                return $fallback;
            }

            $settings = OrderStatusSetting::query()
                ->active()
                ->orderBy('sort_order')
                ->pluck($field, 'code')
                ->all();

            return $settings ?: $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
