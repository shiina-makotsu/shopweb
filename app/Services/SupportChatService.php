<?php

namespace App\Services;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;

class SupportChatService
{
    public function assign(SupportChatSession $session, User $admin): void
    {
        $session->forceFill([
            'assigned_admin_id' => $admin->id,
            'status' => SupportChatSession::STATUS_ACTIVE,
            'ended_at' => null,
            'deleted_by_customer_at' => null,
        ])->save();
    }

    public function reply(SupportChatSession $session, User $admin, string $message): void
    {
        if ($session->assigned_admin_id !== $admin->id || $session->status !== SupportChatSession::STATUS_ACTIVE) {
            $this->assign($session, $admin);
            $session->refresh();
        }

        $session->messages()->create([
            'sender_user_id' => $admin->id,
            'sender_type' => SupportChatMessage::SENDER_ADMIN,
            'body' => $message,
        ]);

        $session->update([
            'last_message_at' => now(),
            'status' => SupportChatSession::STATUS_ACTIVE,
            'ended_at' => null,
        ]);
    }

    public function end(SupportChatSession $session, ?User $admin = null): void
    {
        if ($admin && ! $session->assigned_admin_id) {
            $session->assigned_admin_id = $admin->id;
        }

        $session->forceFill([
            'status' => SupportChatSession::STATUS_ENDED,
            'ended_at' => now(),
            'served_count' => $session->served_count + 1,
        ])->save();
    }
}
