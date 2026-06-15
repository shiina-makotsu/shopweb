<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_user_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('image_endpoint', 2048)->nullable();
            $table->text('image_api_key')->nullable();
            $table->string('chat_endpoint', 2048)->nullable();
            $table->text('chat_api_key')->nullable();
            $table->string('image_model')->nullable();
            $table->string('chat_model')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('ai_image_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running')->index();
            $table->boolean('stream')->default(false);
            $table->text('prompt')->nullable();
            $table->text('submitted_prompt')->nullable();
            $table->json('references')->nullable();
            $table->json('config')->nullable();
            $table->json('images')->nullable();
            $table->json('partials')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('elapsed_ms')->default(0);
            $table->unsignedInteger('actual_width')->nullable();
            $table->unsignedInteger('actual_height')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'deleted_at', 'created_at']);
        });

        Schema::create('ai_chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('新会话');
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'deleted_at', 'updated_at']);
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content')->nullable();
            $table->json('files')->nullable();
            $table->string('model')->nullable();
            $table->string('reasoning_mode')->nullable();
            $table->string('reasoning_label')->nullable();
            $table->boolean('is_error')->default(false);
            $table->timestamps();

            $table->index(['ai_chat_session_id', 'created_at']);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'profit_formula')) {
                $table->text('profit_formula')->nullable()->after('ai_default_user_quota_k');
            }

            if (! Schema::hasColumn('site_settings', 'ai_trash_retention_days')) {
                $table->unsignedInteger('ai_trash_retention_days')->default(30)->after('profit_formula');
            }
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('media_assets', 'content_hash')) {
                $table->string('content_hash', 64)->nullable()->after('path')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
        Schema::dropIfExists('ai_image_tasks');
        Schema::dropIfExists('ai_user_configs');

        Schema::table('site_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('site_settings', 'profit_formula')) {
                $table->dropColumn('profit_formula');
            }

            if (Schema::hasColumn('site_settings', 'ai_trash_retention_days')) {
                $table->dropColumn('ai_trash_retention_days');
            }
        });

        Schema::table('media_assets', function (Blueprint $table): void {
            if (Schema::hasColumn('media_assets', 'content_hash')) {
                $table->dropColumn('content_hash');
            }
        });
    }
};
