<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('ai_default_endpoint', 2048)->nullable();
            $table->text('ai_default_api_key')->nullable();
            $table->unsignedInteger('ai_default_user_quota_k')->default(100);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('ai_quota_k')->nullable();
            $table->string('ai_endpoint', 2048)->nullable();
            $table->text('ai_api_key')->nullable();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature')->default('image')->index();
            $table->string('config_name')->nullable();
            $table->string('provider_source')->default('backend_default')->index();
            $table->string('model')->nullable()->index();
            $table->string('endpoint_host')->nullable();
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('request_ms')->nullable();
            $table->string('status')->default('success')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['model', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_quota_k',
                'ai_endpoint',
                'ai_api_key',
            ]);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_default_endpoint',
                'ai_default_api_key',
                'ai_default_user_quota_k',
            ]);
        });
    }
};
