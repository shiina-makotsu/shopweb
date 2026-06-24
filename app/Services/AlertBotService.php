<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AlertBotService
{
    public function notify(string $title, string $message, array $context = [], string $priority = 'P3'): void
    {
        if (! (bool) config('shop.alert_bot.enabled', false)) {
            return;
        }

        $cooldown = max(0, (int) config('shop.alert_bot.cooldown_seconds', 300));
        $priority = strtoupper($priority);
        $key = 'shop:alert-bot:'.sha1($priority.'|'.$title.'|'.$message);

        if ($cooldown > 0 && Cache::has($key)) {
            return;
        }

        if ($cooldown > 0) {
            Cache::put($key, true, $cooldown);
        }

        try {
            $this->send($title, $message, $context, $priority);
        } catch (Throwable $exception) {
            Log::warning('Alert bot notification failed.', [
                'title' => $title,
                'priority' => $priority,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function send(string $title, string $message, array $context, string $priority): void
    {
        $driver = (string) config('shop.alert_bot.driver', 'webhook');
        $webhookUrl = trim((string) config('shop.alert_bot.webhook_url', ''));
        $timeout = max(1, (int) config('shop.alert_bot.timeout_seconds', 5));

        if ($webhookUrl === '') {
            return;
        }

        $payload = [
            'driver' => $driver,
            'target' => config('shop.alert_bot.target'),
            'token' => config('shop.alert_bot.token'),
            'priority' => $priority,
            'title' => $title,
            'message' => $message,
            'context' => $context,
            'time' => now()->toDateTimeString(),
            'app' => config('app.name', 'ShopWeb'),
        ];

        Http::timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->acceptJson()
            ->post($webhookUrl, $payload);
    }
}
