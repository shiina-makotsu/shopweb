<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $record->markAsRead();

        return back()->with('status', '订单超时提示已确认。');
    }
}
