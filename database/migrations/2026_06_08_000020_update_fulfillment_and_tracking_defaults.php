<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('fulfillment_type', 'shipping_required')
            ->update(['fulfillment_type' => 'logistics']);

        DB::table('products')
            ->where('fulfillment_type', 'contact_only')
            ->update(['fulfillment_type' => 'online']);

        DB::table('site_settings')->update([
            'show_tracking_numbers_to_users' => true,
        ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('fulfillment_type', 'logistics')
            ->update(['fulfillment_type' => 'shipping_required']);

        DB::table('products')
            ->where('fulfillment_type', 'online')
            ->update(['fulfillment_type' => 'contact_only']);

        DB::table('site_settings')->update([
            'show_tracking_numbers_to_users' => false,
        ]);
    }
};
