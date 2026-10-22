<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $bot): JsonResponse
    {
        $settings = SiteSetting::query()->first();
        $secret = (string) $settings?->telegram_webhook_secret;
        abort_unless($settings?->telegram_bot_enabled && $secret !== ''
            && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);
        $bot->handleUpdate($request->json()->all());

        return response()->json(['ok' => true]);
    }
}
