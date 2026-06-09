<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'public_id')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'email', 'public_id'])
            ->whereNull('public_id')
            ->orWhere('public_id', '')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user): void {
                $base = $user->email === 'admin@example.com' ? 'admin' : 'user_'.$user->id;
                $publicId = $base;
                $index = 2;

                while (DB::table('users')->where('public_id', $publicId)->where('id', '!=', $user->id)->exists()) {
                    $publicId = $base.'_'.$index++;
                }

                DB::table('users')->where('id', $user->id)->update(['public_id' => $publicId]);
            });
    }

    public function down(): void
    {
        //
    }
};
