<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (! Schema::hasColumn('coupons', 'is_stackable')) {
                $table->boolean('is_stackable')->default(true)->after('per_user_limit')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            if (Schema::hasColumn('coupons', 'is_stackable')) {
                $table->dropColumn('is_stackable');
            }
        });
    }
};
