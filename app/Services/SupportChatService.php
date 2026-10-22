<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportQuickReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportChatService
{
    public function assign(SupportChatSession $session, User $admin): void
    {
        if ($session->isClosed()) {
            return;
        }

        DB::transaction(function () use ($session, $admin): void {
            SiteSetting::query()->lockForUpdate()->first();
            $locked = SupportChatSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->isClosed()) {
                throw ValidationException::withMessages(['message' => '该会话已关闭。']);
            }
            if ($locked->status === SupportChatSession::STATUS_ACTIVE && $locked->assigned_admin_id && $locked->assigned_admin_id !== $admin->id) {
                throw ValidationException::withMessages(['message' => '该会话已由其他客服接待。']);
            }
            $locked->forceFill([
                'assigned_admin_id' => $admin->id,
                'status' => SupportChatSession::STATUS_ACTIVE,
                'ended_at' => null,
                'deleted_by_customer_at' => null,
                'telegram_queued_at' => null,
            ])->save();
        }, 3);
        $session->refresh();
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    public function reply(
        SupportChatSession $session,
        User $admin,
        ?string $message,
        array $attachment = [],
        ?SupportQuickReply $quickReply = null,
    ): SupportChatMessage {
        return DB::transaction(function () use ($session, $admin, $message, $attachment, $quickReply): SupportChatMessage {
            SiteSetting::query()->lockForUpdate()->first();
            $locked = SupportChatSession::query()->lockForUpdate()->findOrFail($session->id);

            return $this->replyLocked($locked, $admin, $message, $attachment, $quickReply);
        }, 3);
    }

    private function replyLocked(
        SupportChatSession $session,
        User $admin,
        ?string $message,
        array $attachment,
        ?SupportQuickReply $quickReply,
    ): SupportChatMessage {
        if ($session->isClosed()) {
            throw ValidationException::withMessages([
                'message' => '该会话窗口已被用户关闭，不能继续回复。',
            ]);
        }

        $body = trim((string) $message);

        if ($body === '' && $attachment === []) {
            throw ValidationException::withMessages([
                'message' => '请输入消息内容，或选择图片/文件后发送。',
            ]);
        }

        if ($session->assigned_admin_id !== $admin->id || $session->status !== SupportChatSession::STATUS_ACTIVE) {
            $this->assign($session, $admin);
            $session->refresh();
        }

        $session->messages()
            ->whereIn('sender_type', [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $reply = $session->messages()->create([
            'support_quick_reply_id' => $quickReply?->id,
            'sender_user_id' => $admin->id,
            'sender_type' => SupportChatMessage::SENDER_ADMIN,
            'body' => $body === '' ? null : $body,
            'contact_links' => $quickReply ? $this->contactLinks($quickReply) : null,
            ...$attachment,
        ]);

        $session->update([
            'last_message_at' => now(),
            'status' => SupportChatSession::STATUS_ACTIVE,
            'ended_at' => null,
        ]);

        return $reply;
    }

    public function startForUser(User $customer, User $admin, string $message): SupportChatSession
    {
        $session = SupportChatSession::query()
            ->where('user_id', $customer->id)
            ->whereIn('status', [SupportChatSession::STATUS_OPEN, SupportChatSession::STATUS_ACTIVE, SupportChatSession::STATUS_ENDED])
            ->whereNull('deleted_by_customer_at')
            ->latest('last_message_at')
            ->first();

        if (! $session) {
            $session = SupportChatSession::query()->create([
                'user_id' => $customer->id,
                'assigned_admin_id' => $admin->id,
                'status' => SupportChatSession::STATUS_ACTIVE,
                'last_message_at' => now(),
            ]);
        }

        $this->assign($session, $admin);
        $session->refresh();

        $this->reply($session, $admin, $message);

        return $session->fresh();
    }

    public function applyQuickReplyRules(SupportChatSession $session, string $message): ?SupportQuickReply
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        $reply = SupportQuickReply::query()
            ->active()
            ->forTrigger(SupportQuickReply::TRIGGER_MESSAGE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->first(fn (SupportQuickReply $quickReply): bool => $quickReply->matches($message));

        if (! $reply) {
            return null;
        }

        $this->sendQuickReply($session, $reply);

        return $reply;
    }

    public function applyEntryReplies(SupportChatSession $session): void
    {
        if ($session->isClosed() || $session->isEnded()) {
            return;
        }

        SupportQuickReply::query()
            ->active()
            ->forTrigger(SupportQuickReply::TRIGGER_SESSION_ENTRY)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->each(function (SupportQuickReply $reply) use ($session): void {
                DB::transaction(function () use ($session, $reply): void {
                    SiteSetting::query()->lockForUpdate()->first();
                    $lockedSession = SupportChatSession::query()->lockForUpdate()->find($session->id);

                    if (! $lockedSession || $lockedSession->isClosed() || $lockedSession->isEnded()) {
                        return;
                    }

                    $alreadySent = $lockedSession->messages()
                        ->where('support_quick_reply_id', $reply->id)
                        ->exists();

                    if (! $alreadySent) {
                        $this->sendQuickReply($lockedSession, $reply);
                    }
                });
            });
    }

    private function sendQuickReply(SupportChatSession $session, SupportQuickReply $reply): SupportChatMessage
    {
        $body = trim((string) $reply->body);

        if ($body === '') {
            $body = match ($reply->trigger_action) {
                SupportQuickReply::ACTION_AI => '已接入 AI 客服，请继续描述你的问题。',
                SupportQuickReply::ACTION_NOTIFY_STAFF => '已提醒客服接待，请稍候。',
                default => '收到。',
            };
        }

        if ($reply->trigger_action === SupportQuickReply::ACTION_AI) {
            $settings = SiteSetting::query()->first();
            $aiPrompt = trim((string) $settings?->support_ai_system_prompt);
            if ($aiPrompt !== '') {
                $body = $aiPrompt."\n\n".$body;
            }
        }

        $message = $session->messages()->create([
            'support_quick_reply_id' => $reply->id,
            'sender_type' => SupportChatMessage::SENDER_SYSTEM,
            'body' => $body,
            'contact_links' => $this->contactLinks($reply),
        ]);

        $session->update(['last_message_at' => now()]);

        return $message;
    }

    /** @return array<int, array{name:string, account:string, url:string, icon:string}> */
    private function contactLinks(SupportQuickReply $reply): array
    {
        return $reply->contactMethods()
            ->map(fn ($method): ?array => $method->linkData())
            ->filter()
            ->values()
            ->all();
    }

    public function end(SupportChatSession $session, ?User $admin = null): void
    {
        $this->finish($session, false, $admin);
    }

    public function closeByCustomer(SupportChatSession $session): void
    {
        $this->finish($session, true);
    }

    private function finish(SupportChatSession $session, bool $close, ?User $admin = null): void
    {
        DB::transaction(function () use ($session, $close, $admin): void {
            SiteSetting::query()->lockForUpdate()->first();
            $locked = SupportChatSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($locked->isClosed() || (! $close && $locked->isEnded())) {
                return;
            }
            $locked->forceFill([
                'assigned_admin_id' => $locked->assigned_admin_id ?: $admin?->id,
                'status' => $close ? SupportChatSession::STATUS_CLOSED : SupportChatSession::STATUS_ENDED,
                'ended_at' => now(),
                'deleted_by_customer_at' => $close ? now() : null,
                'served_count' => $locked->served_count + ($locked->isEnded() ? 0 : 1),
            ])->save();
        }, 3);
        $session->refresh();
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
