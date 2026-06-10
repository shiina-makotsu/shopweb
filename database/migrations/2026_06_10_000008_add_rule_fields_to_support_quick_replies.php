<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_quick_replies', function (Blueprint $table): void {
            $table->text('match_pattern')->nullable()->after('category');
            $table->string('match_mode', 20)->default('keyword')->after('match_pattern');
            $table->string('trigger_action', 20)->default('reply')->after('match_mode');
        });
    }

    public function down(): void
    {
        Schema::table('support_quick_replies', function (Blueprint $table): void {
            $table->dropColumn(['match_pattern', 'match_mode', 'trigger_action']);
        });
    }
};
