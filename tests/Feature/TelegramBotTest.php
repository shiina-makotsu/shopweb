<?php

use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\TelegramOutbox;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\SupportChatService;
use App\Services\TelegramBotService;
use App\Services\TelegramTransport;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    SiteSetting::query()->create([
        'site_name' => 'Test Shop',
        'telegram_bot_enabled' => true,
        'telegram_bot_token' => '123456:test_token',
        'telegram_webhook_secret' => 'test-secret',
    ]);
    $messageId = 100;
    Http::fake(function () use (&$messageId) {
        return Http::response(['ok' => true, 'result' => ['message_id' => ++$messageId, 'username' => 'test_shop_bot']]);
    });
});

function telegramTestAdmin(string $id, string $role = 'support'): User
{
    $admin = User::factory()->create([
        'role' => $role, 'telegram_user_id' => $id, 'telegram_chat_id' => $id,
        'telegram_notifications_enabled' => true, 'telegram_max_chat_windows' => 1,
    ]);
    $admin->forceFill(['telegram_chat_id' => $id])->save();

    return $admin;
}

function telegramTestUpdate(int $updateId, string $adminId, ?string $text = null, ?string $callback = null): array
{
    $message = ['chat' => ['id' => (int) $adminId, 'type' => 'private'], 'from' => ['id' => (int) $adminId], 'text' => $text];

    return ['update_id' => $updateId] + ($callback
        ? ['callback_query' => ['id' => 'callback-'.$updateId, 'from' => $message['from'], 'data' => $callback, 'message' => $message]]
        : ['message' => $message]);
}

function telegramTestSession(?User $customer = null): SupportChatSession
{
    $session = SupportChatSession::query()->create([
        'user_id' => ($customer ?: User::factory()->create())->id,
        'status' => SupportChatSession::STATUS_OPEN, 'last_message_at' => now(),
    ]);
    $session->messages()->create(['sender_type' => SupportChatMessage::SENDER_CUSTOMER, 'body' => '请帮我查询订单']);

    return $session->fresh();
}

it('authenticates webhooks and only binds an explicitly configured private Telegram user', function (): void {
    $admin = telegramTestAdmin('101');
    $admin->update(['telegram_chat_id' => null]);
    $this->postJson(route('telegram.webhook'), telegramTestUpdate(1, '101', '/start'))->assertForbidden();
    $this->postJson(route('telegram.webhook'), telegramTestUpdate(2, '999', '/start'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();
    expect($admin->fresh()->telegram_chat_id)->toBeNull();
    $this->postJson(route('telegram.webhook'), telegramTestUpdate(3, '101', '/start'), ['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])->assertOk();
    expect($admin->fresh()->telegram_chat_id)->toBe('101');
    expect($admin->fresh()->toArray())->not->toHaveKey('telegram_chat_id');
    expect(SiteSetting::query()->first()->toArray())->not->toHaveKey('telegram_bot_token');
    expect(DB::table('site_settings')->value('telegram_bot_token'))->not->toBe('123456:test_token');
});

it('lets only the first administrator accept a session and deduplicates updates', function (): void {
    $one = telegramTestAdmin('101');
    telegramTestAdmin('202');
    $session = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    $bot->handleUpdate(telegramTestUpdate(2, '202', callback: 'accept:'.$session->id));
    $bot->handleUpdate(telegramTestUpdate(3, '101', '收到，我来帮你。'));
    $bot->handleUpdate(telegramTestUpdate(3, '101', '收到，我来帮你。'));
    expect($session->fresh()->assigned_admin_id)->toBe($one->id)
        ->and($session->fresh()->telegram_admin_id)->toBe($one->id)
        ->and($session->messages()->where('sender_type', SupportChatMessage::SENDER_ADMIN)->count())->toBe(1);
    expect(fn () => app(SupportChatService::class)->assign($session->fresh(), User::factory()->create(['role' => 'admin'])))
        ->toThrow(ValidationException::class);
});

it('queues when all windows are full and automatically assigns the oldest after end', function (): void {
    $admin = telegramTestAdmin('101');
    $bot = app(TelegramBotService::class);
    $first = telegramTestSession();
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$first->id));
    $second = telegramTestSession();
    $third = telegramTestSession();
    expect($second->telegram_queued_at)->not->toBeNull();
    $bot->handleUpdate(telegramTestUpdate(2, '101', callback: 'accept:'.$second->id));
    expect($second->fresh()->assigned_admin_id)->toBeNull();
    $bot->handleUpdate(telegramTestUpdate(3, '101', '/end'));
    expect($first->fresh()->status)->toBe(SupportChatSession::STATUS_ENDED)
        ->and($first->fresh()->served_count)->toBe(1)
        ->and($second->fresh()->telegram_admin_id)->toBe($admin->id)
        ->and($third->fresh()->assigned_admin_id)->toBeNull()
        ->and($admin->fresh()->telegram_active_support_session_id)->toBe($second->id);
});

it('closes Telegram access when the customer deletes a session and releases the window', function (): void {
    $admin = telegramTestAdmin('101');
    $bot = app(TelegramBotService::class);
    $session = telegramTestSession();
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    app(SupportChatService::class)->closeByCustomer($session->fresh());
    $bot->handleUpdate(telegramTestUpdate(2, '101', '这条不应该发送'));
    expect($session->fresh()->telegram_admin_id)->toBeNull()
        ->and($admin->fresh()->telegram_active_support_session_id)->toBeNull()
        ->and($session->messages()->where('body', '这条不应该发送')->exists())->toBeFalse();
});

it('supports chat by public user ID and prevents non-support roles from accepting', function (): void {
    $admin = telegramTestAdmin('101');
    telegramTestAdmin('202', 'finance');
    $customer = User::factory()->create(['public_id' => 'customer_one']);
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', '/chat customer_one'));
    $session = SupportChatSession::query()->sole();
    expect($session->user_id)->toBe($customer->id)->and($session->telegram_admin_id)->toBe($admin->id);
    $other = telegramTestSession();
    $bot->handleUpdate(telegramTestUpdate(2, '202', callback: 'accept:'.$other->id));
    expect($other->fresh()->assigned_admin_id)->toBeNull();
});

it('routes Telegram replies to the exact quoted session when multiple windows are open', function (): void {
    $admin = telegramTestAdmin('101');
    $admin->update(['telegram_max_chat_windows' => 2]);
    $first = telegramTestSession();
    $second = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$first->id));
    $bot->handleUpdate(telegramTestUpdate(2, '101', callback: 'accept:'.$second->id));
    app(TelegramTransport::class)->flush(100);
    $link = DB::table('telegram_message_links')->where('support_chat_session_id', $first->id)->first();
    expect($link)->not->toBeNull();
    $update = telegramTestUpdate(3, '101', '回复第一个客户');
    $update['message']['reply_to_message'] = ['message_id' => $link->telegram_message_id];
    $bot->handleUpdate($update);
    expect($first->messages()->where('body', '回复第一个客户')->exists())->toBeTrue()
        ->and($second->messages()->where('body', '回复第一个客户')->exists())->toBeFalse();
});

it('sends order and refund alerts to all bound administrators without duplicating cancellation alerts', function (): void {
    telegramTestAdmin('101', 'finance');
    telegramTestAdmin('202');
    $customer = User::factory()->create();
    $order = Order::query()->create(['user_id' => $customer->id, 'order_number' => 'TG-ORDER', 'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING, 'subtotal_cents' => 1000, 'total_cents' => 1000, 'contact_name' => 'Test', 'contact_phone' => '123']);
    $order->update(['status' => Order::STATUS_CANCELLED]);
    $order->update(['customer_note' => 'test']);
    WalletTransaction::query()->create(['user_id' => $customer->id, 'order_id' => $order->id, 'type' => 'credit',
        'source' => WalletTransaction::SOURCE_ORDER_REFUND, 'amount_cents' => 1000, 'balance_after_cents' => 1000]);
    expect(TelegramOutbox::query()->count())->toBe(6);
    $texts = TelegramOutbox::query()->get()->map(fn ($entry) => $entry->payload['text']);
    expect($texts->filter(fn ($text) => str_contains($text, '订单已取消'))->count())->toBe(2)
        ->and($texts->filter(fn ($text) => str_contains($text, '订单退款已入钱包'))->count())->toBe(2);
});

it('retains failed notifications and retries without exposing tokens in stored payloads', function (): void {
    telegramTestAdmin('101');
    app(TelegramTransport::class)->sendText('101', '需要重试的消息');
    Http::swap(new Factory);
    Http::fake(fn () => Http::response(['ok' => false], 500));
    expect(app(TelegramTransport::class)->flush())->toBe(0);
    $entry = TelegramOutbox::query()->sole();
    expect($entry->attempts)->toBe(1)->and($entry->sent_at)->toBeNull();
    expect(DB::table('telegram_outbox')->value('payload'))->not->toContain('需要重试的消息');
    $this->travel(1)->minutes();
    Http::swap(new Factory);
    Http::fake(fn () => Http::response(['ok' => true, 'result' => ['message_id' => 10]]));
    expect(app(TelegramTransport::class)->flush())->toBe(1)->and($entry->fresh()->sent_at)->not->toBeNull();
});

it('receives Telegram files into private support storage', function (): void {
    Storage::fake('support_attachments');
    telegramTestAdmin('101');
    $session = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    Http::swap(new Factory);
    Http::fake([
        'https://api.telegram.org/bot*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file.txt']]),
        'https://api.telegram.org/file/*' => Http::response('File content'),
    ]);
    $update = telegramTestUpdate(2, '101');
    $update['message']['document'] = ['file_id' => 'file1', 'file_name' => 'help.txt', 'mime_type' => 'text/plain', 'file_size' => 12];
    $bot->handleUpdate($update);
    $message = $session->messages()->where('sender_type', SupportChatMessage::SENDER_ADMIN)->sole();
    Storage::disk('support_attachments')->assertExists($message->attachment_path);
    expect($message->attachment_original_name)->toBe('help.txt');
});

it('requeues active sessions when an administrator disables Telegram and drops stale notifications', function (): void {
    $admin = telegramTestAdmin('101');
    $session = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    $admin->refresh()->update(['telegram_notifications_enabled' => false]);
    expect($session->fresh()->assigned_admin_id)->toBeNull()
        ->and($session->fresh()->telegram_admin_id)->toBeNull()
        ->and($session->fresh()->telegram_queued_at)->not->toBeNull()
        ->and($admin->fresh()->telegram_active_support_session_id)->toBeNull();
    expect(app(TelegramTransport::class)->flush(100))->toBe(1);
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.telegram.org/bot123456:test_token/sendMessage');
});

it('rejects stale website replies and does not count the same reception twice', function (): void {
    $admin = telegramTestAdmin('101');
    $session = telegramTestSession();
    $stale = $session->fresh();
    $chat = app(SupportChatService::class);
    $chat->end($session, $admin);
    $chat->end($stale, $admin);
    $chat->closeByCustomer($session->fresh());
    expect($session->fresh()->served_count)->toBe(1);
    expect(fn () => $chat->reply($stale, $admin, '已经关闭，不得发送'))
        ->toThrow(ValidationException::class);
});

it('does not create notifications for orders rolled back before commit', function (): void {
    telegramTestAdmin('101');
    $customer = User::factory()->create();
    try {
        DB::transaction(function () use ($customer): void {
            Order::query()->create(['user_id' => $customer->id, 'order_number' => 'ROLLBACK-TG', 'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_PENDING, 'subtotal_cents' => 1000, 'total_cents' => 1000, 'contact_name' => 'Test', 'contact_phone' => '123']);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(TelegramOutbox::query()->count())->toBe(0);
});

it('keeps messages addressed to ended windows from falling back to the current customer', function (): void {
    $admin = telegramTestAdmin('101');
    $admin->update(['telegram_max_chat_windows' => 2]);
    $first = telegramTestSession();
    $second = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$first->id));
    $bot->handleUpdate(telegramTestUpdate(2, '101', callback: 'accept:'.$second->id));
    app(TelegramTransport::class)->flush(100);
    $link = DB::table('telegram_message_links')->where('support_chat_session_id', $first->id)->first();
    app(SupportChatService::class)->closeByCustomer($first->fresh());
    $update = telegramTestUpdate(3, '101', '不可误发的消息');
    $update['message']['reply_to_message'] = ['message_id' => $link->telegram_message_id];
    $bot->handleUpdate($update);
    expect(SupportChatMessage::query()->where('body', '不可误发的消息')->exists())->toBeFalse();
});

it('ends idle Telegram sessions through the scheduled sync and receives the next queued customer', function (): void {
    $admin = telegramTestAdmin('101');
    $first = telegramTestSession();
    app(TelegramBotService::class)->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$first->id));
    $second = telegramTestSession();
    $first->update(['last_message_at' => now()->subMinutes(61)]);
    $this->artisan('shop:telegram-sync')->assertSuccessful();
    expect($first->fresh()->status)->toBe(SupportChatSession::STATUS_ENDED)
        ->and($second->fresh()->telegram_admin_id)->toBe($admin->id);
});

it('serves untrusted chat documents as downloads instead of executable pages', function (): void {
    Storage::fake('support_attachments');
    $customer = User::factory()->create();
    $session = telegramTestSession($customer);
    Storage::disk('support_attachments')->put('page.html', '<html><script>alert(1)</script></html>');
    $message = $session->messages()->create(['sender_type' => SupportChatMessage::SENDER_ADMIN,
        'attachment_path' => 'page.html', 'attachment_original_name' => 'page.html', 'attachment_mime_type' => 'text/html']);
    $this->actingAs($customer)->get(route('support.messages.attachment', $message))
        ->assertOk()->assertDownload('page.html')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rolls back Telegram updates on transient attachment failures so the same update can retry', function (): void {
    Storage::fake('support_attachments');
    telegramTestAdmin('101');
    $session = telegramTestSession();
    $bot = app(TelegramBotService::class);
    $bot->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    Http::swap(new Factory);
    Http::fake([
        'https://api.telegram.org/bot*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file.txt']]),
        'https://api.telegram.org/file/*' => Http::response('', 503),
    ]);
    $update = telegramTestUpdate(2, '101');
    $update['message']['document'] = ['file_id' => 'file1', 'file_name' => 'retry.txt', 'mime_type' => 'text/plain', 'file_size' => 12];
    expect(fn () => $bot->handleUpdate($update))->toThrow(RuntimeException::class);
    expect(DB::table('telegram_updates')->where('update_id', 2)->exists())->toBeFalse();
    Http::swap(new Factory);
    Http::fake([
        'https://api.telegram.org/bot*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file.txt']]),
        'https://api.telegram.org/file/*' => Http::response('File content'),
    ]);
    $bot->handleUpdate($update);
    expect($session->messages()->where('attachment_original_name', 'retry.txt')->count())->toBe(1);
});

it('retries old undelivered messages after more than ten failures', function (): void {
    telegramTestAdmin('101');
    app(TelegramTransport::class)->sendText('101', '连接恢复后应发送');
    $entry = TelegramOutbox::query()->sole();
    $entry->update(['attempts' => 12, 'available_at' => now()->subMinute()]);
    expect(app(TelegramTransport::class)->flush())->toBe(1)->and($entry->fresh()->sent_at)->not->toBeNull();
});

it('forwards the receptionist divider once on acceptance', function (): void {
    $admin = telegramTestAdmin('101');
    $session = telegramTestSession();
    app(TelegramBotService::class)->handleUpdate(telegramTestUpdate(1, '101', callback: 'accept:'.$session->id));
    $welcome = '客服 '.$admin->displayName().' 为您服务';
    expect(TelegramOutbox::query()->get()->filter(fn ($entry) => str_contains($entry->payload['text'] ?? '', $welcome))->count())->toBe(1);
});

it('denies unrelated backoffice users access to private support attachments', function (): void {
    Storage::fake('support_attachments');
    $session = telegramTestSession();
    Storage::disk('support_attachments')->put('private.txt', 'private');
    $message = $session->messages()->create(['sender_type' => SupportChatMessage::SENDER_ADMIN,
        'attachment_path' => 'private.txt', 'attachment_original_name' => 'private.txt']);
    $this->actingAs(User::factory()->create(['role' => 'warehouse']))
        ->get(route('support.messages.attachment', $message))->assertForbidden();
});
