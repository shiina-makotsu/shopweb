<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 120)->nullable()->index();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable()->index();
            $table->string('path', 1024)->nullable();
            $table->string('referrer', 1024)->nullable();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('amount_cents')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['product_id', 'event', 'created_at']);
            $table->index(['order_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
