<?php

namespace App\Observers;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Services\TelegramBotService;

class TelegramSupportObserver
{
    public function created(SupportChatMessage|SupportChatSession $message): void
    {
        if (! $message instanceof SupportChatMessage) {
            return;
        }
        if ($message->sender_type === SupportChatMessage::SENDER_ADMIN && request()->is('telegram/webhook')) {
            return;
        }
        app(TelegramBotService::class)->notifyMessage($message);
    }

    public function updated(SupportChatSession|SupportChatMessage $session): void
    {
        if (! $session instanceof SupportChatSession) {
            return;
        }
        $adminId = $session->getRawOriginal('telegram_admin_id');
        if (! $adminId || ! $session->wasChanged(['status', 'assigned_admin_id'])) {
            return;
        }
        if ($session->isEnded() || $session->isClosed() || $session->assigned_admin_id !== (int) $adminId) {
            app(TelegramBotService::class)->sessionReleased($session->id, (int) $adminId);
        }
    }
}
