<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('catalog_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('text');
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('delivery_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sold_out_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('behavior')->default('hide');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('quantity_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('precision')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('manufacturer_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('manufacturer_id')->constrained()->nullOnDelete();
            $table->foreignId('delivery_status_id')->nullable()->after('fulfillment_type')->constrained()->nullOnDelete();
            $table->foreignId('sold_out_status_id')->nullable()->after('delivery_status_id')->constrained()->nullOnDelete();
            $table->foreignId('quantity_unit_id')->nullable()->after('sold_out_status_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quantity_unit_id');
            $table->dropConstrainedForeignId('sold_out_status_id');
            $table->dropConstrainedForeignId('delivery_status_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('manufacturer_id');
        });

        Schema::dropIfExists('quantity_units');
        Schema::dropIfExists('sold_out_statuses');
        Schema::dropIfExists('delivery_statuses');
        Schema::dropIfExists('catalog_attributes');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('manufacturers');
    }
};
