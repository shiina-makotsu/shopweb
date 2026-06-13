<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'nickname')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('nickname')
            ->where('nickname', '!=', '')
            ->update([
                'name' => DB::raw('nickname'),
            ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('nickname');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'nickname')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('nickname')->nullable()->after('forum_posting_ban_reason');
        });
    }
};
