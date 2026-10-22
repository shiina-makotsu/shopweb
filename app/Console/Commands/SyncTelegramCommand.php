<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Models\SupportChatSession;
use App\Models\TelegramOutbox;
use App\Services\TelegramBotService;
use App\Services\TelegramTransport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTelegramCommand extends Command
{
    protected $signature = 'shop:telegram-sync';

    protected $description = '结束超时的 Telegram 客服接待、分配排队会话并重试通知';

    public function handle(TelegramBotService $bot, TelegramTransport $transport): int
    {
        if (! SiteSetting::query()->first()?->telegram_bot_enabled) {
            return self::SUCCESS;
        }
        SupportChatSession::query()->whereNotNull('telegram_admin_id')
            ->where('status', SupportChatSession::STATUS_ACTIVE)
            ->where('last_message_at', '<=', now()->subMinutes(SupportChatSession::CUSTOMER_IDLE_MINUTES))
            ->each(fn (SupportChatSession $session) => $session->endIfIdle());
        $bot->dispatchQueue();
        $sent = $transport->flush();
        DB::table('telegram_updates')->where('created_at', '<', now()->subDays(7))->delete();
        TelegramOutbox::query()->where('sent_at', '<', now()->subDays(30))->delete();
        $this->info("已发送 {$sent} 条 Telegram 通知。");

        return self::SUCCESS;
    }
}
