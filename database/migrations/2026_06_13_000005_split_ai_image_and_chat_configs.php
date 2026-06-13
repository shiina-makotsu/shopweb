<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('site_settings', 'ai_default_image_endpoint')) {
                $table->string('ai_default_image_endpoint', 2048)->nullable()->after('ai_default_api_key');
            }

            if (! Schema::hasColumn('site_settings', 'ai_default_image_api_key')) {
                $table->text('ai_default_image_api_key')->nullable()->after('ai_default_image_endpoint');
            }

            if (! Schema::hasColumn('site_settings', 'ai_default_chat_endpoint')) {
                $table->string('ai_default_chat_endpoint', 2048)->nullable()->after('ai_default_image_api_key');
            }

            if (! Schema::hasColumn('site_settings', 'ai_default_chat_api_key')) {
                $table->text('ai_default_chat_api_key')->nullable()->after('ai_default_chat_endpoint');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'ai_image_endpoint')) {
                $table->string('ai_image_endpoint', 2048)->nullable()->after('ai_api_key');
            }

            if (! Schema::hasColumn('users', 'ai_image_api_key')) {
                $table->text('ai_image_api_key')->nullable()->after('ai_image_endpoint');
            }

            if (! Schema::hasColumn('users', 'ai_chat_endpoint')) {
                $table->string('ai_chat_endpoint', 2048)->nullable()->after('ai_image_api_key');
            }

            if (! Schema::hasColumn('users', 'ai_chat_api_key')) {
                $table->text('ai_chat_api_key')->nullable()->after('ai_chat_endpoint');
            }
        });

        if (Schema::hasColumn('site_settings', 'ai_default_endpoint')) {
            DB::table('site_settings')->whereNull('ai_default_image_endpoint')->update([
                'ai_default_image_endpoint' => DB::raw('ai_default_endpoint'),
            ]);
            DB::table('site_settings')->whereNull('ai_default_chat_endpoint')->update([
                'ai_default_chat_endpoint' => DB::raw('ai_default_endpoint'),
            ]);
        }

        if (Schema::hasColumn('site_settings', 'ai_default_api_key')) {
            DB::table('site_settings')->whereNull('ai_default_image_api_key')->update([
                'ai_default_image_api_key' => DB::raw('ai_default_api_key'),
            ]);
            DB::table('site_settings')->whereNull('ai_default_chat_api_key')->update([
                'ai_default_chat_api_key' => DB::raw('ai_default_api_key'),
            ]);
        }

        if (Schema::hasColumn('users', 'ai_endpoint')) {
            DB::table('users')->whereNull('ai_image_endpoint')->update([
                'ai_image_endpoint' => DB::raw('ai_endpoint'),
            ]);
            DB::table('users')->whereNull('ai_chat_endpoint')->update([
                'ai_chat_endpoint' => DB::raw('ai_endpoint'),
            ]);
        }

        if (Schema::hasColumn('users', 'ai_api_key')) {
            DB::table('users')->whereNull('ai_image_api_key')->update([
                'ai_image_api_key' => DB::raw('ai_api_key'),
            ]);
            DB::table('users')->whereNull('ai_chat_api_key')->update([
                'ai_chat_api_key' => DB::raw('ai_api_key'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_image_endpoint',
                'ai_image_api_key',
                'ai_chat_endpoint',
                'ai_chat_api_key',
            ]);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_default_image_endpoint',
                'ai_default_image_api_key',
                'ai_default_chat_endpoint',
                'ai_default_chat_api_key',
            ]);
        });
    }
};
