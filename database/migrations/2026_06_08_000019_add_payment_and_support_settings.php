<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('payment_qr_path')->nullable();
            $table->string('payment_account_name')->nullable();
            $table->string('payment_account_note')->nullable();
            $table->boolean('payment_auto_check_enabled')->default(true);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('payment_auto_checked_at')->nullable();
            $table->string('payment_auto_check_status')->nullable();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category')->default('other')->index();
            $table->string('subject');
            $table->longText('message');
            $table->string('status')->default('open')->index();
            $table->text('admin_reply')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_auto_checked_at', 'payment_auto_check_status']);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_qr_path',
                'payment_account_name',
                'payment_account_note',
                'payment_auto_check_enabled',
            ]);
        });
    }
};
