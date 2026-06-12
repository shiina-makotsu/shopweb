<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            $table->text('path')->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table): void {
            $table->string('path')->change();
        });
    }
};
