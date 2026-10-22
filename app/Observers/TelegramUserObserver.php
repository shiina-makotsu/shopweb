<?php

namespace App\Observers;

use App\Models\SiteSetting;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;

class TelegramUserObserver
{
    public function updated(User $user): void
    {
        if (! $user->wasChanged(['telegram_user_id', 'telegram_chat_id', 'telegram_notifications_enabled', 'role', 'telegram_max_chat_windows'])) {
            return;
        }
        $bindingChanged = $user->wasChanged('telegram_user_id');
        DB::afterCommit(function () use ($user, $bindingChanged): void {
            $fresh = $user->fresh();
            if (! $fresh) {
                return;
            }
            if ($bindingChanged || ! $fresh->telegram_chat_id || ! $fresh->telegram_notifications_enabled || ! AdminAccess::can('support', $fresh)) {
                DB::transaction(function () use ($fresh): void {
                    SiteSetting::query()->lockForUpdate()->first();
                    $fresh->forceFill(['telegram_active_support_session_id' => null])->save();
                    SupportChatSession::query()->where('telegram_admin_id', $fresh->id)
                        ->where('status', SupportChatSession::STATUS_ACTIVE)->lockForUpdate()->get()
                        ->each(function (SupportChatSession $session): void {
                            $session->forceFill([
                                'assigned_admin_id' => null, 'telegram_admin_id' => null,
                                'status' => SupportChatSession::STATUS_OPEN, 'telegram_queued_at' => now(),
                            ])->save();
                        });
                }, 3);
            }
            app(TelegramBotService::class)->dispatchQueue();
        });
    }
}
