<?php

namespace App\Services;

use App\Models\AdminLoginLog;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Request;

class AdminLoginLogger
{
    public function successful(User $user, Request $request): void
    {
        if (! AdminAccess::canAccessPanel($user)) {
            return;
        }

        $this->create($request, $user->email, true, $user);
    }

    public function failed(?string $email, Request $request): void
    {
        if (! $email) {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if (! AdminAccess::canAccessPanel($user)) {
            return;
        }

        $this->create($request, $email, false, $user, 'invalid_credentials');
    }

    private function create(Request $request, ?string $email, bool $successful, ?User $user = null, ?string $failureReason = null): void
    {
        AdminLoginLog::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'role' => $user?->role,
            'successful' => $successful,
            'failure_reason' => $failureReason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
