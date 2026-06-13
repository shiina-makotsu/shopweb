<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_chat_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('support_chat_messages', 'quoted_message_id')) {
                $table->unsignedBigInteger('quoted_message_id')->nullable()->index()->after('sender_type');
            }

            if (! Schema::hasColumn('support_chat_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index()->after('read_at');
            }
        });

        Schema::table('private_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('private_messages', 'quoted_message_id')) {
                $table->unsignedBigInteger('quoted_message_id')->nullable()->index()->after('recipient_id');
            }

            if (! Schema::hasColumn('private_messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('body');
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
                $table->string('attachment_mime_type')->nullable()->after('attachment_original_name');
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
            }

            if (! Schema::hasColumn('private_messages', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('private_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('private_messages', 'attachment_size')) {
                $table->dropColumn([
                    'attachment_path',
                    'attachment_original_name',
                    'attachment_mime_type',
                    'attachment_size',
                ]);
            }

            if (Schema::hasColumn('private_messages', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            if (Schema::hasColumn('private_messages', 'quoted_message_id')) {
                $table->dropColumn('quoted_message_id');
            }
        });

        Schema::table('support_chat_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('support_chat_messages', 'quoted_message_id')) {
                $table->dropColumn('quoted_message_id');
            }

            if (Schema::hasColumn('support_chat_messages', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
