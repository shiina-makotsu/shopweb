<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_id')->nullable()->unique()->after('id');
        });

        DB::table('users')
            ->select(['id', 'email'])
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->whereNull('public_id')
                    ->update([
                        'public_id' => $user->email === 'admin@example.com' ? 'admin' : 'user_'.$user->id,
                    ]);
            });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->longText('welcome_message')->nullable();
            $table->text('copyright_text')->nullable();
            $table->text('guide_pet_system_prompt')->nullable();
            $table->string('guide_pet_api_key')->nullable();
        });

        if (Schema::hasColumn('site_settings', 'theme_template')) {
            DB::table('site_settings')
                ->where('theme_template', 'litecart')
                ->update(['theme_template' => 'default']);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('comments_enabled')->default(true)->index();
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->unsignedInteger('discount_price_cents')->nullable();
            $table->timestamp('discount_starts_at')->nullable();
            $table->timestamp('discount_ends_at')->nullable();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('guest_id')->nullable()->index();
            $table->string('guest_email')->nullable();
        });

        Schema::create('product_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_comments')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->longText('body');
            $table->json('image_paths')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->boolean('is_published')->default(true)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('comments_enabled')->default(false);
            $table->boolean('popup_when_unread')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['announcement_id', 'user_id']);
        });

        Schema::create('forum_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('forum_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body');
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['forum_section_id', 'slug']);
        });

        Schema::create('forum_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('forum_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('forum_comments')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('private_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->longText('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_messages');
        Schema::dropIfExists('forum_comments');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forum_sections');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('product_comments');

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['guest_id', 'guest_email']);
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropColumn(['discount_price_cents', 'discount_starts_at', 'discount_ends_at']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('comments_enabled');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'welcome_message',
                'copyright_text',
                'guide_pet_system_prompt',
                'guide_pet_api_key',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('public_id');
        });
    }
};
