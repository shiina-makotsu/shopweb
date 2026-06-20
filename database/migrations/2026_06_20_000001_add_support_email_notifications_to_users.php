<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'support_email_notifications_enabled')) {
                $table->boolean('support_email_notifications_enabled')->default(false)->after('can_view_tracking_numbers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'support_email_notifications_enabled')) {
                $table->dropColumn('support_email_notifications_enabled');
            }
        });
    }
};
