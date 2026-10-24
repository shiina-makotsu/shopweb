<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\TelegramOutbox;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TelegramTransport
{
    public function client(): PendingRequest
    {
        $client = Http::connectTimeout(5)->timeout(15)->withoutRedirecting();
        $proxy = config('services.telegram.proxy_url');
        if (filled($proxy)) {
            $client->withOptions(['proxy' => $proxy]);
        }

        return $client;
    }

    public function request(string $method, array $payload = [], ?string $attachmentPath = null, ?string $attachmentName = null): array
    {
        // Read through the model so the encrypted token is decrypted.
        $token = SiteSetting::query()->first()?->telegram_bot_token;
        if (! filled($token)) {
            throw new RuntimeException('请先保存 Telegram Bot Token。');
        }

        $stream = null;
        try {
            $http = $this->client();
            if ($attachmentPath !== null) {
                $stream = Storage::disk('support_attachments')->readStream($attachmentPath);
                if (! is_resource($stream)) {
                    throw new RuntimeException('客服附件不存在。');
                }
                $field = $method === 'sendPhoto' ? 'photo' : 'document';
                $http = $http->attach($field, $stream, basename($attachmentName ?: $attachmentPath));
                foreach ($payload as $key => $value) {
                    if (is_array($value)) {
                        $payload[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            $response = $http->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
            if (! $response->successful() || $response->json('ok') !== true) {
                throw new RuntimeException('Telegram 接口调用失败，请检查 Token、网络和机器人权限。');
            }

            return (array) $response->json('result', []);
        } catch (Throwable) {
            // HTTP exceptions can contain the token in their URL; never expose them.
            throw new RuntimeException('Telegram 接口暂时不可用，请检查 Token、网络和机器人权限。');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function queue(string $method, array $payload): void
    {
        if (! SiteSetting::query()->first()?->telegram_bot_enabled) {
            return;
        }
        if (isset($payload['chat_id'])) {
            $payload['_recipient_user_id'] = User::query()->where('telegram_chat_id', (string) $payload['chat_id'])->value('id');
        }

        TelegramOutbox::query()->create([
            'method' => $method,
            'payload' => $payload,
            'available_at' => now(),
        ]);
    }

    public function sendText(string $chatId, string $text, array $buttons = [], ?int $sessionId = null, bool $isCommandReply = false): void
    {
        $payload = ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000)];
        if ($buttons !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $buttons];
        }
        if ($sessionId !== null) {
            $payload['_session_id'] = $sessionId;
        }
        if ($isCommandReply) {
            $payload['_command_reply'] = true;
        }
        $this->queue('sendMessage', $payload);
    }

    public function flush(int $limit = 50, int $timeBudgetSeconds = 45): int
    {
        $settings = SiteSetting::query()->first();
        if (! $settings?->telegram_bot_enabled || ! filled($settings->telegram_bot_token)) {
            return 0;
        }

        $sent = 0;
        $startedAt = microtime(true);
        $ids = TelegramOutbox::query()->whereNull('sent_at')
            ->where('available_at', '<=', now())
            ->orderByRaw("CASE WHEN method = 'answerCallbackQuery' THEN 0 ELSE 1 END")
            ->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            if (microtime(true) - $startedAt >= $timeBudgetSeconds) {
                break;
            }
            $sent += DB::transaction(function () use ($id): int {
                $entry = TelegramOutbox::query()->lockForUpdate()->find($id);
                if (! $entry || $entry->sent_at || $entry->available_at?->isFuture()) {
                    return 0;
                }
                try {
                    $payload = $entry->payload;
                    $sessionId = $payload['_session_id'] ?? null;
                    $attachment = $payload['_attachment_path'] ?? null;
                    $attachmentName = $payload['_attachment_name'] ?? null;
                    $recipientId = $payload['_recipient_user_id'] ?? null;
                    $isCommandReply = (bool) ($payload['_command_reply'] ?? false);
                    if ($recipientId) {
                        $recipient = User::query()->find($recipientId);
                        if (! $recipient || ! AdminAccess::canAccessPanel($recipient)
                            || (! $recipient->telegram_notifications_enabled && ! $isCommandReply)
                            || $recipient->telegram_chat_id !== (string) $payload['chat_id']
                            || ($sessionId && ! AdminAccess::can('support', $recipient))) {
                            $entry->update(['sent_at' => now()]);

                            return 0;
                        }
                    }
                    unset($payload['_session_id'], $payload['_attachment_path'], $payload['_attachment_name'], $payload['_recipient_user_id'], $payload['_command_reply']);
                    $result = $this->request($entry->method, $payload, $attachment, $attachmentName);
                    if ($sessionId && isset($result['message_id'], $payload['chat_id'])) {
                        DB::table('telegram_message_links')->insertOrIgnore([
                            'chat_id' => (string) $payload['chat_id'],
                            'telegram_message_id' => $result['message_id'],
                            'support_chat_session_id' => $sessionId,
                            'created_at' => now(),
                        ]);
                    }
                    $entry->update(['sent_at' => now(), 'attempts' => min(65535, $entry->attempts + 1)]);

                    return 1;
                } catch (Throwable) {
                    $attempts = min(65535, $entry->attempts + 1);
                    $entry->update(['attempts' => $attempts, 'available_at' => now()->addSeconds(min(3600, 15 * (2 ** min(8, $attempts))))]);
                    Log::warning('Telegram 通知发送失败，等待重试。', ['outbox_id' => $entry->id, 'attempts' => $attempts]);

                    return 0;
                }
            });
        }

        return $sent;
    }
}
