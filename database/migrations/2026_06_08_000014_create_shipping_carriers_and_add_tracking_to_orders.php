<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carriers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('tracking_url_template')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->text('api_notes')->nullable();
            $table->boolean('is_international')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('shipping_carrier_id')->nullable()->after('shipping_address')->constrained()->nullOnDelete();
            $table->string('tracking_number')->nullable()->after('shipping_carrier_id');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shipping_carrier_id');
            $table->dropColumn([
                'tracking_number',
                'tracking_url',
                'shipped_at',
                'delivered_at',
            ]);
        });

        Schema::dropIfExists('shipping_carriers');
    }
};
