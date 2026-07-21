<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_contact_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('account')->nullable();
            $table->string('url', 2048);
            $table->string('icon')->default('fa-solid fa-comments');
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('support_quick_replies', function (Blueprint $table): void {
            $table->string('trigger_event', 30)->default('message')->after('category')->index();
            $table->json('contact_method_ids')->nullable()->after('body');
        });

        Schema::table('support_chat_messages', function (Blueprint $table): void {
            $table->foreignId('support_quick_reply_id')
                ->nullable()
                ->after('quoted_message_id')
                ->constrained('support_quick_replies')
                ->nullOnDelete();
            $table->json('contact_links')->nullable()->after('body');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->timestamp('customer_read_at')->nullable()->after('admin_reply')->index();
        });

        Schema::table('after_sales_requests', function (Blueprint $table): void {
            $table->timestamp('customer_read_at')->nullable()->after('admin_note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('after_sales_requests', function (Blueprint $table): void {
            $table->dropColumn('customer_read_at');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn('customer_read_at');
        });

        Schema::table('support_chat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('support_quick_reply_id');
            $table->dropColumn('contact_links');
        });

        Schema::table('support_quick_replies', function (Blueprint $table): void {
            $table->dropColumn(['trigger_event', 'contact_method_ids']);
        });

        Schema::dropIfExists('support_contact_methods');
    }
};
