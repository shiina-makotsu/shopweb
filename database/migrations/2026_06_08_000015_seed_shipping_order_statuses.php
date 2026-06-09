<?php

use App\Models\OrderStatusSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_status_settings')) {
            OrderStatusSetting::seedDefaults();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_status_settings')) {
            return;
        }

        OrderStatusSetting::query()
            ->whereIn('code', ['pending_shipment', 'incoming', 'shipped', 'awaiting_receipt'])
            ->delete();
    }
};
