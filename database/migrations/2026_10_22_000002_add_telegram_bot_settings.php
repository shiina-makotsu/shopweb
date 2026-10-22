<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('telegram_bot_enabled')->default(false);
            $table->text('telegram_bot_token')->nullable();
            $table->string('telegram_bot_username')->nullable();
            $table->string('telegram_webhook_secret')->nullable();
            $table->timestamp('telegram_webhook_registered_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('telegram_user_id', 32)->nullable()->unique();
            $table->string('telegram_chat_id', 32)->nullable();
            $table->string('telegram_username')->nullable();
            $table->boolean('telegram_notifications_enabled')->default(true);
            $table->unsignedSmallInteger('telegram_max_chat_windows')->default(1);
            $table->unsignedBigInteger('telegram_active_support_session_id')->nullable();
        });

        Schema::table('support_chat_sessions', function (Blueprint $table): void {
            $table->foreignId('telegram_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('telegram_queued_at')->nullable();
        });

        Schema::create('telegram_updates', function (Blueprint $table): void {
            $table->unsignedBigInteger('update_id')->primary();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('telegram_message_links', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id', 32);
            $table->unsignedBigInteger('telegram_message_id');
            $table->foreignId('support_chat_session_id')->nullable()->constrained('support_chat_sessions')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['chat_id', 'telegram_message_id']);
        });

        Schema::create('telegram_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('method');
            $table->text('payload');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['sent_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_outbox');
        Schema::dropIfExists('telegram_message_links');
        Schema::dropIfExists('telegram_updates');

        Schema::table('support_chat_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('telegram_admin_id');
            $table->dropColumn('telegram_queued_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telegram_user_id']);
            $table->dropColumn([
                'telegram_user_id',
                'telegram_chat_id',
                'telegram_username',
                'telegram_notifications_enabled',
                'telegram_max_chat_windows',
                'telegram_active_support_session_id',
            ]);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'telegram_bot_enabled',
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_webhook_secret',
                'telegram_webhook_registered_at',
            ]);
        });
    }
};
