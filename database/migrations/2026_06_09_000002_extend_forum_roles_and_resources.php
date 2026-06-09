<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('forum_role')->default('member')->index()->after('account_type');
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('library')->default('site')->index()->after('usage');
            $table->foreignId('uploaded_by_id')->nullable()->after('library')->constrained('users')->nullOnDelete();
        });

        Schema::create('forum_moderators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['forum_section_id', 'user_id']);
        });

        Schema::table('forum_threads', function (Blueprint $table): void {
            $table->json('attachment_paths')->nullable()->after('body');
            $table->unsignedInteger('likes_count')->default(0)->index()->after('is_pinned');
            $table->unsignedInteger('shares_count')->default(0)->index()->after('likes_count');
            $table->foreignId('deleted_by_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('deleted_by_id');
        });

        Schema::table('forum_comments', function (Blueprint $table): void {
            $table->json('attachment_paths')->nullable()->after('body');
            $table->unsignedInteger('likes_count')->default(0)->index()->after('attachment_paths');
            $table->foreignId('deleted_by_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('deleted_by_id');
        });

        Schema::create('forum_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('forum_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('forum_comment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('target_type')->nullable()->index();
            $table->string('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_activity_logs');

        Schema::table('forum_comments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by_id');
            $table->dropColumn(['attachment_paths', 'likes_count', 'edited_at']);
        });

        Schema::table('forum_threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by_id');
            $table->dropColumn(['attachment_paths', 'likes_count', 'shares_count', 'edited_at']);
        });

        Schema::dropIfExists('forum_moderators');

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('uploaded_by_id');
            $table->dropColumn('library');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('forum_role');
        });
    }
};
