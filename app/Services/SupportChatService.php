<?php

namespace App\Services;

use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SupportChatService
{
    public function assign(SupportChatSession $session, User $admin): void
    {
        if ($session->isClosed()) {
            return;
        }

        $session->forceFill([
            'assigned_admin_id' => $admin->id,
            'status' => SupportChatSession::STATUS_ACTIVE,
            'ended_at' => null,
            'deleted_by_customer_at' => null,
        ])->save();
    }

    public function reply(SupportChatSession $session, User $admin, string $message): void
    {
        if ($session->isClosed()) {
            throw ValidationException::withMessages([
                'message' => '该会话窗口已被用户关闭，不能继续回复。',
            ]);
        }

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
        if ($session->isClosed()) {
            return;
        }

        if ($admin && ! $session->assigned_admin_id) {
            $session->assigned_admin_id = $admin->id;
        }

        $session->forceFill([
            'status' => SupportChatSession::STATUS_ENDED,
            'ended_at' => now(),
            'served_count' => $session->served_count + 1,
        ])->save();
    }

    public function closeByCustomer(SupportChatSession $session): void
    {
        if ($session->isClosed()) {
            return;
        }

        $session->forceFill([
            'status' => SupportChatSession::STATUS_CLOSED,
            'ended_at' => now(),
            'deleted_by_customer_at' => now(),
            'served_count' => $session->served_count + 1,
        ])->save();
    }

    public function comfortIfIdle(SupportChatSession $session): void
    {
        if ($session->assigned_admin_id || $session->isClosed() || $session->isEnded()) {
            return;
        }

        $settings = SiteSetting::query()->first();

        if (! ($settings?->support_ai_enabled ?? false)) {
            return;
        }

        $idleMinutes = max(1, (int) ($settings->support_ai_idle_minutes ?: 10));
        $lastMessageAt = $session->last_message_at ?: $session->created_at;

        if (! $lastMessageAt || $lastMessageAt->gt(now()->subMinutes($idleMinutes))) {
            return;
        }

        if ($session->messages()
            ->where('sender_type', SupportChatMessage::SENDER_SYSTEM)
            ->where('created_at', '>=', $lastMessageAt)
            ->exists()) {
            return;
        }

        $body = trim((string) $settings->support_ai_system_prompt);
        $body = $body !== ''
            ? $body
            : '客服暂时还没有接入，我会先陪你等一下。你可以继续补充问题、订单信息或截图，客服看到后会尽快处理。';

        $session->messages()->create([
            'sender_type' => SupportChatMessage::SENDER_SYSTEM,
            'body' => $body,
        ]);

        $session->update(['last_message_at' => now()]);
    }
}
