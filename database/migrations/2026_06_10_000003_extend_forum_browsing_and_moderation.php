<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_sections', function (Blueprint $table): void {
            $table->string('posting_policy')->default('all')->index()->after('is_active');
            $table->text('admin_note')->nullable()->after('posting_policy');
        });

        Schema::table('forum_threads', function (Blueprint $table): void {
            $table->unsignedInteger('views_count')->default(0)->index()->after('shares_count');
            $table->boolean('is_locked')->default(false)->index()->after('is_pinned');
            $table->boolean('is_featured')->default(false)->index()->after('is_locked');
            $table->timestamp('last_replied_at')->nullable()->index()->after('edited_at');
            $table->text('admin_note')->nullable()->after('last_replied_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('forum_posting_banned_at')->nullable()->index()->after('forum_role');
            $table->string('forum_posting_ban_reason')->nullable()->after('forum_posting_banned_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['forum_posting_banned_at', 'forum_posting_ban_reason']);
        });

        Schema::table('forum_threads', function (Blueprint $table): void {
            $table->dropColumn(['views_count', 'is_locked', 'is_featured', 'last_replied_at', 'admin_note']);
        });

        Schema::table('forum_sections', function (Blueprint $table): void {
            $table->dropColumn(['posting_policy', 'admin_note']);
        });
    }
};
