<?php

use App\Support\AdminAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'public_id')) {
            return;
        }

        DB::table('users')
            ->select(['id', 'email', 'role', 'public_id'])
            ->whereIn('role', AdminAccess::panelRoles())
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user): void {
                $current = (string) ($user->public_id ?? '');

                if (str_starts_with(Str::lower($current), 'staff_')) {
                    return;
                }

                $base = $user->email === 'admin@example.com'
                    ? 'staff_admin'
                    : 'staff_'.$user->id;

                $publicId = $base;
                $index = 2;

                while (DB::table('users')->where('public_id', $publicId)->where('id', '!=', $user->id)->exists()) {
                    $publicId = substr($base, 0, 40 - strlen((string) $index) - 1).'_'.$index++;
                }

                DB::table('users')->where('id', $user->id)->update(['public_id' => $publicId]);
            });
    }

    public function down(): void
    {
        //
    }
};
