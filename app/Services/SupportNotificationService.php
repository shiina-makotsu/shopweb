<?php

namespace App\Services;

use App\Mail\SupportMessagePendingMail;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SupportNotificationService
{
    public function notifyPendingMessage(SupportChatSession $session, SupportChatMessage $message): void
    {
        if (! in_array($message->sender_type, [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST], true)) {
            return;
        }

        app(AlertBotService::class)->notify('ShopWeb P2 客户客服消息', '有新的客户客服消息等待处理。', [
            'session_id' => $session->id,
            'message_id' => $message->id,
            'sender_type' => $message->sender_type,
            'order_id' => $session->order_id,
            'user_id' => $session->user_id,
        ], 'P2');

        $settings = SiteSetting::query()->first();

        if (! $this->mailEnabled($settings)) {
            return;
        }

        if (! Schema::hasColumn('users', 'support_email_notifications_enabled')) {
            return;
        }

        $recipients = User::query()
            ->whereIn('role', [AdminAccess::ROLE_ADMIN, AdminAccess::ROLE_SUPPORT])
            ->where('support_email_notifications_enabled', true)
            ->whereNotNull('email')
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $originalMailConfig = [
            'mailers.smtp' => config('mail.mailers.smtp'),
            'from' => config('mail.from'),
        ];

        try {
            config([
                'mail.mailers.smtp.host' => $settings->mail_host,
                'mail.mailers.smtp.port' => $settings->mail_port ?: 587,
                'mail.mailers.smtp.encryption' => $settings->mail_encryption ?: null,
                'mail.mailers.smtp.username' => $settings->mail_username,
                'mail.mailers.smtp.password' => $settings->mail_password,
                'mail.from.address' => $settings->mail_from_address,
                'mail.from.name' => $settings->mail_from_name ?: $settings->site_name ?: config('app.name', 'ShopWeb'),
            ]);

            $session->loadMissing(['user', 'order']);

            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(new SupportMessagePendingMail($session, $message, $settings));
            }
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            config([
                'mail.mailers.smtp' => $originalMailConfig['mailers.smtp'],
                'mail.from' => $originalMailConfig['from'],
            ]);
        }
    }

    private function mailEnabled(?SiteSetting $settings): bool
    {
        return filled($settings?->mail_host)
            && filled($settings?->mail_from_address);
    }
}
