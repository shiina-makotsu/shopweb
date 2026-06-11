<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pages', 'template')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->string('template')->default('default')->index()->after('slug');
            });
        }

        if (! Schema::hasColumn('forum_threads', 'template')) {
            Schema::table('forum_threads', function (Blueprint $table): void {
                $table->string('template')->nullable()->index()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('forum_threads', 'template')) {
            Schema::table('forum_threads', function (Blueprint $table): void {
                $table->dropColumn('template');
            });
        }

        if (Schema::hasColumn('pages', 'template')) {
            Schema::table('pages', function (Blueprint $table): void {
                $table->dropColumn('template');
            });
        }
    }
};
