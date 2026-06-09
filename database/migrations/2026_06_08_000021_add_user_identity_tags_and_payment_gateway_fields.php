<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type')->default('regular')->index();
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('payment_gateway_provider')->default('manual');
            $table->json('payment_enabled_methods')->nullable();
            $table->json('payment_gateway_config')->nullable();
            $table->text('payment_gateway_notes')->nullable();
        });

        Schema::create('product_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_product_tag', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'product_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product_tag');
        Schema::dropIfExists('product_tags');

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_gateway_provider',
                'payment_enabled_methods',
                'payment_gateway_config',
                'payment_gateway_notes',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
