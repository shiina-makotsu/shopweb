<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_status_settings')) {
            return;
        }

        DB::table('order_status_settings')
            ->where('code', Order::STATUS_AWAITING_RECEIPT)
            ->where('label', '待签收')
            ->update(['label' => '待收货']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_status_settings')) {
            return;
        }

        DB::table('order_status_settings')
            ->where('code', Order::STATUS_AWAITING_RECEIPT)
            ->where('label', '待收货')
            ->update(['label' => '待签收']);
    }
};
