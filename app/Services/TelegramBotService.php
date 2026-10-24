<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\AdminAccess;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TelegramBotService
{
    public function __construct(private readonly TelegramTransport $transport) {}

    public function configureWebhook(): void
    {
        $settings = SiteSetting::query()->firstOrFail();
        $url = route('telegram.webhook');
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('请先把 APP_URL 设置为公网 HTTPS 地址。');
        }
        $identity = $this->transport->request('getMe');
        $secret = $settings->telegram_webhook_secret ?: Str::random(48);
        $settings->forceFill(['telegram_webhook_secret' => $secret])->save();
        $this->transport->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);
        $settings->forceFill([
            'telegram_bot_username' => $identity['username'] ?? null,
            'telegram_webhook_registered_at' => now(),
        ])->save();
    }

    public function handleUpdate(array $update): void
    {
        DB::transaction(function () use ($update): void {
            if (! isset($update['update_id']) || ! is_int($update['update_id']) || $update['update_id'] < 0) {
                return;
            }
            if (! DB::table('telegram_updates')->insertOrIgnore(['update_id' => $update['update_id'], 'created_at' => now()])) {
                return;
            }
            // Serialize claims and window counts across all administrators.
            SiteSetting::query()->lockForUpdate()->firstOrFail();
            $callback = $update['callback_query'] ?? null;
            $message = $callback['message'] ?? ($update['message'] ?? []);
            $from = $callback['from'] ?? ($message['from'] ?? []);
            $chatId = (string) ($message['chat']['id'] ?? '');
            if (($message['chat']['type'] ?? null) !== 'private' || $chatId !== (string) ($from['id'] ?? '')) {
                return;
            }
            $admin = User::query()->whereIn('role', AdminAccess::panelRoles())
                ->where('telegram_user_id', (string) ($from['id'] ?? ''))->lockForUpdate()->first();
            if (! $admin) {
                $this->transport->sendText($chatId,
                    "你的 Telegram Chat ID：{$chatId}\n尚未绑定后台账号。请由超级管理员将此数字填写到后台用户的 Telegram Chat ID，保存后再发送 /start 完成验证。不要填写 @用户名或 t.me 链接。");
                if ($callback) {
                    $this->answer($callback['id'], '账号尚未绑定。');
                }

                return;
            }
            $admin->forceFill(['telegram_chat_id' => $chatId, 'telegram_username' => $from['username'] ?? null])->save();
            try {
                if ($callback) {
                    $this->callback($admin, $callback);
                } else {
                    $this->incoming($admin, $message);
                }
            } catch (ValidationException $exception) {
                $text = collect($exception->errors())->flatten()->first() ?: '操作失败，请重试。';
                $this->transport->sendText($chatId, $text);
                if ($callback) {
                    $this->answer($callback['id'], $text);
                }
            }
        }, 3);
    }

    public function notifyOrder(Order $order, string $event): void
    {
        foreach ($this->recipients() as $admin) {
            $customer = $order->user?->public_id ?: (string) $order->user_id;
            $this->transport->sendText($admin->telegram_chat_id,
                "{$event}\n订单：{$order->order_number}\n用户：{$customer}\n金额：".Money::format($order->paymentTotalCents()));
        }
    }

    public function notifyRefund(WalletTransaction $transaction): void
    {
        foreach ($this->recipients() as $admin) {
            $this->transport->sendText($admin->telegram_chat_id,
                "订单退款已入钱包\n订单：".($transaction->order?->order_number ?: '-')
                ."\n用户：".($transaction->user?->public_id ?: $transaction->user_id)
                ."\n退款金额：".Money::format($transaction->amount_cents));
        }
    }

    public function notifyMessage(SupportChatMessage $message): void
    {
        DB::transaction(function () use ($message): void {
            SiteSetting::query()->lockForUpdate()->first();
            $this->notifyLockedMessage($message);
        }, 3);
    }

    private function notifyLockedMessage(SupportChatMessage $message): void
    {
        $session = $message->session()->lockForUpdate()->first();
        if (! $session || $session->isClosed() || $session->isEnded()) {
            return;
        }
        if ($session->telegram_admin_id) {
            $admin = $this->recipients(true)->firstWhere('id', $session->telegram_admin_id);
            if ($admin) {
                $this->forward($admin, $session, $message);

                return;
            }
            $session->forceFill(['telegram_admin_id' => null, 'assigned_admin_id' => null, 'status' => SupportChatSession::STATUS_OPEN])->save();
        }
        if ($session->assigned_admin_id || ! in_array($message->sender_type, [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST], true)) {
            return;
        }
        $this->queueWaitingWhenFull();
        foreach ($this->recipients(true) as $admin) {
            $this->transport->sendText($admin->telegram_chat_id,
                $this->sessionTitle($session)."\n".mb_substr((string) $message->body, 0, 2000).($message->hasAttachment() ? "\n[附件]" : ''),
                [[['text' => '接受会话', 'callback_data' => 'accept:'.$session->id]]], $session->id);
        }
    }

    public function sessionReleased(int $sessionId, int $adminId): void
    {
        DB::transaction(function () use ($sessionId, $adminId): void {
            SiteSetting::query()->lockForUpdate()->first();
            $session = SupportChatSession::query()->lockForUpdate()->find($sessionId);
            $admin = User::query()->find($adminId);
            if (! $session || ! $admin) {
                return;
            }
            if ($session->telegram_admin_id === $adminId) {
                if (! $session->isEnded() && ! $session->isClosed() && $session->assigned_admin_id === $adminId) {
                    return;
                }
                $session->forceFill(['telegram_admin_id' => null, 'telegram_queued_at' => null])->save();
            }
            User::query()->whereKey($adminId)->where('telegram_active_support_session_id', $sessionId)
                ->update(['telegram_active_support_session_id' => null]);
            if ($admin->telegram_chat_id) {
                $this->transport->sendText($admin->telegram_chat_id, "会话 #{$sessionId} 已结束或已转交，不能继续回复。空闲窗口将自动接入排队用户。");
            }
            $this->dispatchQueue();
        }, 3);
    }

    public function dispatchQueue(): void
    {
        DB::transaction(function (): void {
            SiteSetting::query()->lockForUpdate()->first();
            foreach ($this->recipients(true) as $admin) {
                while ($this->hasCapacity($admin)) {
                    $session = SupportChatSession::query()->where('status', SupportChatSession::STATUS_OPEN)
                        ->whereNull('assigned_admin_id')->whereNotNull('telegram_queued_at')->whereNull('deleted_by_customer_at')
                        ->orderBy('telegram_queued_at')->orderBy('id')->lockForUpdate()->first();
                    if (! $session) {
                        break;
                    }
                    $this->claim($session, $admin);
                }
            }
        }, 3);
    }

    private function callback(User $admin, array $callback): void
    {
        $this->requireSupport($admin);
        if (! preg_match('/^(accept|switch):(\d+)$/D', $callback['data'] ?? '', $parts)) {
            $this->answer($callback['id'], '无效操作。');

            return;
        }
        $session = SupportChatSession::query()->lockForUpdate()->find((int) $parts[2]);
        if (! $session || $session->isEnded() || $session->isClosed()) {
            $this->fail('该会话已经结束。');
        }
        if ($parts[1] === 'accept') {
            if ($session->assigned_admin_id && $session->assigned_admin_id !== $admin->id) {
                $this->fail('该会话已由其他客服接待。');
            }
            if ($session->telegram_admin_id !== $admin->id) {
                if (! $this->hasCapacity($admin)) {
                    $this->fail('你的会话窗口已满；释放窗口后将自动接入排队用户。');
                }
                $this->claim($session, $admin);
            }
        } else {
            $this->requireOwnership($session, $admin);
        }
        $admin->forceFill(['telegram_active_support_session_id' => $session->id])->save();
        $this->answer($callback['id'], "已选择会话 #{$session->id}。");
        $this->transport->sendText($admin->telegram_chat_id, "当前回复会话 #{$session->id}。发送 /end 结束接待。", [], $session->id);
    }

    private function incoming(User $admin, array $message): void
    {
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));
        if (preg_match('~^/(start|help)(?:@\w+)?(?:\s|$)~u', $text)) {
            $this->transport->sendText($admin->telegram_chat_id,
                "你的 Telegram Chat ID：{$admin->telegram_chat_id}\n绑定成功：{$admin->displayName()}\n/chat 网站用户ID：主动联系\n/sessions：查看接待窗口\n/switch 会话编号：切换窗口\n/end：结束当前会话\n也可直接回复某条会话消息。只有客服权限账号可接待。", isCommandReply: true);

            return;
        }
        $this->requireSupport($admin);
        if (preg_match('~^/chat(?:@\w+)?\s+([A-Za-z0-9_]+)$~D', $text, $parts)) {
            if (! $this->hasCapacity($admin)) {
                $this->fail('接待窗口已满，请先使用 /end 结束一个会话。');
            }
            $customer = User::query()->where('public_id', $parts[1])->first();
            if (! $customer) {
                $this->fail('未找到该网站用户 ID。');
            }
            $session = SupportChatSession::query()->create(['user_id' => $customer->id, 'status' => SupportChatSession::STATUS_OPEN, 'last_message_at' => now()]);
            $this->claim($session, $admin);
            $this->transport->sendText($admin->telegram_chat_id, "已联系 {$customer->public_id}，当前会话 #{$session->id}。", [], $session->id);

            return;
        }
        if (preg_match('~^/sessions(?:@\w+)?$~D', $text)) {
            $sessions = SupportChatSession::query()->where('telegram_admin_id', $admin->id)->where('status', SupportChatSession::STATUS_ACTIVE)->get();
            $this->transport->sendText($admin->telegram_chat_id, $sessions->isEmpty() ? '没有正在接待的会话。' : '点击选择回复窗口：',
                $sessions->map(fn ($session) => [['text' => ($admin->telegram_active_support_session_id === $session->id ? '当前 · ' : '').$this->sessionTitle($session), 'callback_data' => 'switch:'.$session->id]])->all());

            return;
        }
        if (preg_match('~^/switch(?:@\w+)?\s+(\d+)$~D', $text, $parts)) {
            $session = $this->ownedSession($admin, (int) $parts[1]);
            $admin->forceFill(['telegram_active_support_session_id' => $session->id])->save();
            $this->transport->sendText($admin->telegram_chat_id, "当前回复会话 #{$session->id}。", [], $session->id);

            return;
        }
        $replyId = $message['reply_to_message']['message_id'] ?? null;
        $sessionId = $replyId ? DB::table('telegram_message_links')->where('chat_id', $admin->telegram_chat_id)
            ->where('telegram_message_id', $replyId)->value('support_chat_session_id') : $admin->telegram_active_support_session_id;
        if (! $sessionId) {
            $this->fail('请先接受会话，或用 /sessions 选择回复窗口。');
        }
        $session = $this->ownedSession($admin, (int) $sessionId);
        if (preg_match('~^/end(?:@\w+)?$~D', $text)) {
            app(SupportChatService::class)->end($session, $admin);

            return;
        }
        if (str_starts_with($text, '/')) {
            $this->fail('未知指令，发送 /help 查看指令。');
        }
        $attachment = $this->receiveAttachment($message, $session);
        if ($text === '' && $attachment === []) {
            $this->fail('请发送文字、图片或文件。');
        }
        app(SupportChatService::class)->reply($session, $admin, mb_substr($text, 0, 3000), $attachment);
    }

    private function claim(SupportChatSession $session, User $admin): void
    {
        $session->forceFill([
            'assigned_admin_id' => $admin->id, 'telegram_admin_id' => $admin->id,
            'telegram_queued_at' => null, 'status' => SupportChatSession::STATUS_ACTIVE, 'ended_at' => null,
        ])->save();
        $this->queueWaitingWhenFull();
        $admin->forceFill(['telegram_active_support_session_id' => $session->id])->save();
        $welcome = $session->messages()->create(['sender_type' => SupportChatMessage::SENDER_SYSTEM, 'body' => '客服 '.$admin->displayName().' 为您服务']);
        app(AdminActivityLogger::class)->log('support.telegram_accept', $session, '通过 Telegram 接受客服会话 #'.$session->id, [], $admin);
        $this->transport->sendText($admin->telegram_chat_id, '已接入 '.$this->sessionTitle($session), [], $session->id);
        foreach ($session->messages()->whereKeyNot($welcome->id)->latest('id')->limit(10)->get()->reverse() as $message) {
            $this->forward($admin, $session, $message);
        }
    }

    private function forward(User $admin, SupportChatSession $session, SupportChatMessage $message): void
    {
        $sender = match ($message->sender_type) {
            SupportChatMessage::SENDER_ADMIN => '客服', SupportChatMessage::SENDER_SYSTEM => '系统', default => '客户',
        };
        $buttons = [[['text' => '回复此会话', 'callback_data' => 'switch:'.$session->id]]];
        $this->transport->sendText($admin->telegram_chat_id,
            $this->sessionTitle($session)."\n{$sender}：".($message->body ?: '[附件]'), $buttons, $session->id);
        if ($message->attachment_path && Storage::disk('support_attachments')->exists($message->attachment_path)) {
            $this->transport->queue($message->isImage() ? 'sendPhoto' : 'sendDocument', [
                'chat_id' => $admin->telegram_chat_id, 'caption' => "会话 #{$session->id} 附件",
                '_attachment_path' => $message->attachment_path, '_session_id' => $session->id,
                '_attachment_name' => $message->attachment_original_name,
            ]);
        }
    }

    private function receiveAttachment(array $message, SupportChatSession $session): array
    {
        $photos = $message['photo'] ?? [];
        $file = $message['document'] ?? ($photos === [] ? null : end($photos));
        if (! is_array($file) || ! isset($file['file_id'])) {
            return [];
        }
        if (($file['file_size'] ?? 0) > 10 * 1024 * 1024) {
            $this->fail('附件不能超过 10MB。');
        }
        $remote = $this->transport->request('getFile', ['file_id' => $file['file_id']]);
        $remotePath = (string) ($remote['file_path'] ?? '');
        if ($remotePath === '' || str_contains($remotePath, '..') || ! preg_match('~^[A-Za-z0-9_./-]+$~D', $remotePath)) {
            $this->fail('附件下载地址无效。');
        }
        $token = SiteSetting::query()->first()?->telegram_bot_token;
        try {
            $response = $this->transport->client()->timeout(20)->get('https://api.telegram.org/file/bot'.$token.'/'.$remotePath);
            if (! $response->successful()) {
                throw new RuntimeException('Telegram 附件暂时下载失败，请重试。');
            }
            if (strlen($response->body()) > 10 * 1024 * 1024) {
                $this->fail('附件超过 10MB。');
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new RuntimeException('Telegram 附件暂时下载失败，请重试。');
        }
        $name = basename($file['file_name'] ?? $remotePath);
        $path = 'session-'.$session->id.'/'.Str::uuid().'.bin';
        if (! Storage::disk('support_attachments')->put($path, $response->body())) {
            throw new RuntimeException('无法保存客服附件，请检查存储目录写入权限。');
        }

        return ['attachment_path' => $path, 'attachment_original_name' => $name,
            'attachment_mime_type' => $photos !== [] ? 'image/jpeg' : ($file['mime_type'] ?? 'application/octet-stream'),
            'attachment_size' => strlen($response->body())];
    }

    private function ownedSession(User $admin, int $id): SupportChatSession
    {
        $session = SupportChatSession::query()->lockForUpdate()->find($id);
        if (! $session) {
            $this->fail('会话不存在。');
        }
        $this->requireOwnership($session, $admin);

        return $session;
    }

    private function requireOwnership(SupportChatSession $session, User $admin): void
    {
        if ($session->isClosed() || $session->isEnded() || $session->telegram_admin_id !== $admin->id || $session->assigned_admin_id !== $admin->id) {
            $this->fail('该会话未由你接待或已结束，请选择其他窗口。');
        }
    }

    private function hasCapacity(User $admin): bool
    {
        return SupportChatSession::query()->where('telegram_admin_id', $admin->id)->where('status', SupportChatSession::STATUS_ACTIVE)->count()
            < max(1, (int) $admin->telegram_max_chat_windows);
    }

    private function queueWaitingWhenFull(): void
    {
        $admins = $this->recipients(true);
        if ($admins->isNotEmpty() && ! $admins->contains(fn (User $admin): bool => $this->hasCapacity($admin))) {
            SupportChatSession::query()->where('status', SupportChatSession::STATUS_OPEN)
                ->whereNull('assigned_admin_id')->whereNull('telegram_queued_at')->whereNull('deleted_by_customer_at')
                ->whereHas('messages')->update(['telegram_queued_at' => now()]);
        }
    }

    private function recipients(bool $supportOnly = false): Collection
    {
        if (! SiteSetting::query()->first()?->telegram_bot_enabled) {
            return collect();
        }

        return User::query()->whereIn('role', AdminAccess::panelRoles())->where('telegram_notifications_enabled', true)
            ->whereNotNull('telegram_user_id')->whereNotNull('telegram_chat_id')->orderBy('id')->get()
            ->filter(fn (User $user): bool => ! $supportOnly || AdminAccess::can('support', $user));
    }

    private function sessionTitle(SupportChatSession $session): string
    {
        return "会话 #{$session->id} · ".($session->user?->public_id ?: $session->guest_id ?: '游客');
    }

    private function requireSupport(User $admin): void
    {
        if (! $admin->telegram_notifications_enabled || ! AdminAccess::can('support', $admin)) {
            $this->fail('当前账号没有客服接待权限或 Telegram 接待已关闭。');
        }
    }

    private function answer(string $callbackId, string $text): void
    {
        $this->transport->queue('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => mb_substr($text, 0, 180)]);
    }

    private function fail(string $text): never
    {
        throw ValidationException::withMessages(['telegram' => $text]);
    }
}
