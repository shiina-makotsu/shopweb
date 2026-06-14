<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('pages', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('sort_order');
            }

            if (! Schema::hasColumn('pages', 'comments_enabled')) {
                $table->boolean('comments_enabled')->default(false)->after('views_count');
            }

            if (! Schema::hasColumn('pages', 'reward_qr_path')) {
                $table->string('reward_qr_path', 2048)->nullable()->after('comments_enabled');
            }
        });

        if (! Schema::hasTable('page_comments')) {
            Schema::create('page_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('page_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('page_comments')->cascadeOnDelete();
                $table->text('body');
                $table->timestamp('deleted_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_comments');

        Schema::table('pages', function (Blueprint $table): void {
            foreach (['reward_qr_path', 'comments_enabled', 'views_count'] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
