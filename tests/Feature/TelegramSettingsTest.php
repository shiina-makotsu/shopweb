<?php

use App\Filament\Pages\TelegramBotSettingsPage;
use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\AdminUserResource\Pages\CreateAdminUser;
use App\Filament\Resources\AdminUserResource\Pages\EditAdminUser;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Support\AdminMenuRegistry;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Http::preventStrayRequests();
    SiteSetting::query()->create(['site_name' => 'Telegram Settings Test']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(function (): void {
    URL::forceRootUrl(null);
    URL::forceScheme(null);
});

it('allows administrators to open the registered Telegram settings page', function (): void {
    $this->get('/admin/telegram-bot-settings')->assertOk();

    Livewire::test(TelegramBotSettingsPage::class)
        ->assertSuccessful()
        ->assertSet('data.telegram_bot_token', '')
        ->assertFormFieldIsDisabled('telegram_bot_username')
        ->assertFormFieldIsDisabled('telegram_webhook_registered_at');

    expect(app(AdminMenuRegistry::class)->pageClasses())->toContain(TelegramBotSettingsPage::class);
    Http::assertNothingSent();
});

it('denies support users access to Telegram settings and direct mutation methods', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'support']));

    $this->get('/admin/telegram-bot-settings')->assertForbidden();
    Livewire::test(TelegramBotSettingsPage::class)->assertForbidden();

    foreach (['save', 'configureWebhook'] as $method) {
        try {
            (new TelegramBotSettingsPage)->{$method}();
            $this->fail('Support users must not mutate bot settings.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }
    }

    Http::assertNothingSent();
});

it('keeps encrypted credentials and registration when saving a blank token', function (): void {
    $settings = SiteSetting::query()->firstOrFail();
    $settings->update([
        'telegram_bot_enabled' => true,
        'telegram_bot_token' => '123456:saved_private_token',
        'telegram_webhook_secret' => 'saved-private-secret',
        'telegram_bot_username' => 'saved_bot',
        'telegram_webhook_registered_at' => now(),
    ]);
    $ciphertext = DB::table('site_settings')->value('telegram_bot_token');
    $registeredAt = $settings->telegram_webhook_registered_at->toDateTimeString();
    $linkId = DB::table('telegram_message_links')->insertGetId([
        'chat_id' => '101', 'telegram_message_id' => 100, 'created_at' => now(),
    ]);
    DB::table('telegram_updates')->insert(['update_id' => 100, 'created_at' => now()]);

    $page = Livewire::test(TelegramBotSettingsPage::class)->assertSet('data.telegram_bot_token', '');
    expect(json_encode($page->snapshot))->not->toContain('123456:saved_private_token', 'saved-private-secret');

    $page->fillForm(['telegram_bot_enabled' => false, 'telegram_bot_token' => ''])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.telegram_bot_token', '');

    expect($settings->refresh()->telegram_bot_enabled)->toBeFalse()
        ->and($settings->telegram_bot_token)->toBe('123456:saved_private_token')
        ->and($settings->telegram_webhook_secret)->toBe('saved-private-secret')
        ->and($settings->telegram_bot_username)->toBe('saved_bot')
        ->and($settings->telegram_webhook_registered_at->toDateTimeString())->toBe($registeredAt)
        ->and(DB::table('site_settings')->value('telegram_bot_token'))->toBe($ciphertext)
        ->not->toBe('123456:saved_private_token')
        ->and($settings->toArray())->not->toHaveKeys(['telegram_bot_token', 'telegram_webhook_secret'])
        ->and(json_encode([$page->snapshot, $page->effects]))->not->toContain('123456:saved_private_token', 'saved-private-secret');
    $this->assertDatabaseHas('telegram_message_links', ['id' => $linkId]);
    $this->assertDatabaseHas('telegram_updates', ['update_id' => 100]);
    Http::assertNothingSent();
});

it('rotates the secret and clears routing and dedup records without deleting support history', function (bool $enabled): void {
    $settings = SiteSetting::query()->firstOrFail();
    $settings->update([
        'telegram_bot_token' => '123456:old_token',
        'telegram_webhook_secret' => 'old-private-secret',
        'telegram_bot_username' => 'old_bot',
        'telegram_webhook_registered_at' => now(),
    ]);
    [$session, $message] = SupportChatSession::withoutEvents(function (): array {
        $session = SupportChatSession::query()->create(['status' => SupportChatSession::STATUS_ACTIVE]);
        $message = $session->messages()->create([
            'sender_type' => SupportChatMessage::SENDER_CUSTOMER,
            'body' => 'Support history must survive bot replacement.',
        ]);

        return [$session, $message];
    });
    DB::table('telegram_message_links')->insert([
        'chat_id' => '101', 'telegram_message_id' => 100,
        'support_chat_session_id' => $session->id, 'created_at' => now(),
    ]);
    DB::table('telegram_updates')->insert(['update_id' => 100, 'created_at' => now()]);

    $page = Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_enabled' => $enabled, 'telegram_bot_token' => '987654:new_private_token'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.telegram_bot_token', '');

    expect($settings->refresh()->telegram_bot_token)->toBe('987654:new_private_token')
        ->and($settings->telegram_webhook_secret)->toHaveLength(64)->not->toBe('old-private-secret')
        ->and($settings->telegram_bot_username)->toBeNull()
        ->and($settings->telegram_webhook_registered_at)->toBeNull()
        ->and(json_encode([$page->snapshot, $page->effects]))->not->toContain('987654:new_private_token', 'old-private-secret', $settings->telegram_webhook_secret);
    $this->assertDatabaseEmpty('telegram_message_links');
    $this->assertDatabaseEmpty('telegram_updates');
    $this->assertDatabaseHas('support_chat_sessions', ['id' => $session->id]);
    $this->assertDatabaseHas('support_chat_messages', ['id' => $message->id, 'body' => $message->body]);
    Http::assertNothingSent();
})->with(['enabled' => true, 'disabled' => false]);

it('preserves the webhook secret when resaving the same token', function (): void {
    $settings = SiteSetting::query()->firstOrFail();
    $settings->update([
        'telegram_bot_token' => '123456:unchanged_token',
        'telegram_webhook_secret' => 'unchanged-private-secret',
    ]);

    Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_enabled' => true, 'telegram_bot_token' => '123456:unchanged_token'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.telegram_bot_token', '');

    expect($settings->refresh()->telegram_webhook_secret)->toBe('unchanged-private-secret');
    Http::assertNothingSent();
});

it('registers a public HTTPS webhook using getMe and setWebhook with the saved secret', function (): void {
    config(['app.url' => 'https://shop.example.test']);
    URL::forceRootUrl('https://shop.example.test');
    URL::forceScheme('https');
    Http::fake([
        'https://api.telegram.org/bot123456:registration_token/getMe' => Http::response([
            'ok' => true, 'result' => ['id' => 123456, 'username' => 'verified_shop_bot'],
        ]),
        'https://api.telegram.org/bot123456:registration_token/setWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $page = Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_enabled' => true, 'telegram_bot_token' => '123456:registration_token'])
        ->callAction('configureWebhook')
        ->assertHasNoFormErrors()
        ->assertSet('data.telegram_bot_username', 'verified_shop_bot')
        ->assertSet('data.telegram_bot_token', '');

    $settings = SiteSetting::query()->firstOrFail();
    expect($settings->telegram_webhook_registered_at)->not->toBeNull()
        ->and($settings->telegram_webhook_secret)->not->toBeEmpty()
        ->and(json_encode([$page->snapshot, $page->effects]))->not->toContain('123456:registration_token', $settings->telegram_webhook_secret);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.telegram.org/bot123456:registration_token/getMe');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.telegram.org/bot123456:registration_token/setWebhook'
        && $request['url'] === 'https://shop.example.test/telegram/webhook'
        && $request['secret_token'] === $settings->telegram_webhook_secret
        && $request['allowed_updates'] === ['message', 'callback_query']);
});

it('requires a token before enabling an unconfigured bot', function (): void {
    Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_enabled' => true, 'telegram_bot_token' => ''])
        ->call('save')
        ->assertHasFormErrors(['telegram_bot_token'])
        ->assertSet('data.telegram_bot_token', '');

    expect(SiteSetting::query()->firstOrFail()->telegram_bot_enabled)->toBeFalse();
    Http::assertNothingSent();
});

it('rejects a malformed nonblank token and clears it from the response', function (string $token): void {
    $page = Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_token' => $token])
        ->call('save')
        ->assertHasFormErrors(['telegram_bot_token' => 'regex'])
        ->assertSet('data.telegram_bot_token', '');

    expect(SiteSetting::query()->firstOrFail()->telegram_bot_token)->toBeNull()
        ->and(json_encode($page->snapshot))->not->toContain($token);
    Http::assertNothingSent();
})->with(['missing separator' => 'not-a-token', 'invalid prefix' => 'bot:private_key', 'spaces' => '123:private key', 'URL' => 'https://api.telegram.org/bot123:private_key']);

it('blocks HTTP webhook registration without making an API call', function (): void {
    config(['app.url' => 'http://shop.example.test']);

    Livewire::test(TelegramBotSettingsPage::class)
        ->fillForm(['telegram_bot_enabled' => true, 'telegram_bot_token' => '123456:valid_token'])
        ->callAction('configureWebhook')
        ->assertHasNoFormErrors();

    expect(SiteSetting::query()->firstOrFail()->telegram_webhook_registered_at)->toBeNull();
    Http::assertNothingSent();
});

it('validates unique binding IDs without allowing chat ID edits', function (): void {
    User::factory()->create(['role' => 'support', 'telegram_user_id' => '123456789']);

    Livewire::test(CreateAdminUser::class)
        ->assertFormFieldDoesNotExist('telegram_chat_id')
        ->fillForm(['telegram_user_id' => '123456789'])
        ->call('create')
        ->assertHasFormErrors(['telegram_user_id' => 'unique']);
});

it('rejects binding IDs that are not canonical positive integers', function (string $id): void {
    Livewire::test(CreateAdminUser::class)
        ->fillForm(['telegram_user_id' => $id])
        ->call('create')
        ->assertHasFormErrors(['telegram_user_id']);
})->with(['zero' => '0', 'negative' => '-1', 'fraction' => '1.5', 'username' => '@operator', 'exponent' => '1e3', 'oversize' => '123456789012345678901234567890123']);

it('accepts a 32-digit binding ID without numeric precision loss', function (): void {
    Livewire::test(CreateAdminUser::class)
        ->fillForm(['telegram_user_id' => '12345678901234567890123456789012'])
        ->call('create')
        ->assertHasFormErrors(['name'])
        ->assertHasNoFormErrors(['telegram_user_id']);
});

it('allows an unchanged binding ID when editing its owner', function (): void {
    $user = User::factory()->create(['role' => 'support', 'telegram_user_id' => '123456789']);

    Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['telegram_user_id' => '123456789', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->telegram_user_id)->toBe('123456789');
});

it('saves a private Telegram Chat ID as an unverified numeric binding', function (string $input): void {
    $page = Livewire::test(CreateAdminUser::class)
        ->assertSee('Telegram Chat ID（私聊）')
        ->assertSee('/start')
        ->assertFormFieldDoesNotExist('telegram_chat_id')
        ->fillForm([
            'name' => 'Telegram Operator',
            'public_id' => 'staff_telegram_operator',
            'email' => 'telegram-operator@example.test',
            'password' => 'operator-password',
            'role' => 'support',
            'telegram_user_id' => $input,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'telegram-operator@example.test')->firstOrFail();

    expect($user->telegram_user_id)->toBe('123456789')
        ->and($user->telegram_chat_id)->toBeNull()
        ->and($user->telegram_username)->toBeNull()
        ->and(Hash::check('operator-password', $user->password))->toBeTrue();
    $page->assertRedirect(AdminUserResource::getUrl());
    Http::assertNothingSent();
})->with(['digits' => '123456789', 'pasted whitespace' => " \t123456789\r\n "]);

it('explains invalid Telegram Chat IDs in Chinese without changing an existing binding', function (string $input): void {
    $user = User::factory()->create(['role' => 'support', 'telegram_user_id' => '123456789']);
    $user->update(['telegram_chat_id' => '123456789', 'telegram_username' => 'verified_operator']);
    $passwordHash = $user->password;

    $page = Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['telegram_user_id' => $input])
        ->call('save')
        ->assertHasFormErrors(['telegram_user_id' => 'regex']);

    expect($page->errors()->get('data.telegram_user_id'))->toContain('请输入机器人 /start 回复的纯数字 Chat ID，不要填写 @用户名或 t.me 链接。')
        ->and($user->refresh()->telegram_user_id)->toBe('123456789')
        ->and($user->telegram_chat_id)->toBe('123456789')
        ->and($user->telegram_username)->toBe('verified_operator')
        ->and($user->password)->toBe($passwordHash);
    Http::assertNothingSent();
})->with([
    'username' => '@shiina_makotsu',
    'profile link' => 'https://t.me/shiina_makotsu',
    'leading zero' => '0123456789',
    'group ID' => '-100123456789',
]);

it('explains duplicate and overlong Telegram Chat IDs in Chinese', function (): void {
    User::factory()->create(['role' => 'support', 'telegram_user_id' => '123456789']);
    $user = User::factory()->create(['role' => 'support']);

    $page = Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['telegram_user_id' => ' 123456789 '])
        ->call('save')
        ->assertHasFormErrors(['telegram_user_id' => 'unique']);

    expect($page->errors()->get('data.telegram_user_id'))->toContain('此 Telegram Chat ID 已绑定其他后台账号。');

    $page->fillForm(['telegram_user_id' => str_repeat('1', 33)])
        ->call('save')
        ->assertHasFormErrors(['telegram_user_id' => 'max']);

    expect($page->errors()->get('data.telegram_user_id'))->toContain('Telegram Chat ID 最多为 32 位数字。')
        ->and($user->refresh()->telegram_user_id)->toBeNull();
});

it('keeps a verified binding when editing unrelated account information', function (): void {
    $user = User::factory()->create(['role' => 'support', 'telegram_user_id' => '123456789']);
    $user->update(['telegram_chat_id' => '123456789', 'telegram_username' => 'verified_operator']);
    $passwordHash = $user->password;

    Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->assertSet('data.telegram_user_id', '123456789')
        ->assertFormFieldDoesNotExist('telegram_chat_id')
        ->assertFormFieldDoesNotExist('telegram_username')
        ->fillForm(['profile_intro' => 'Updated profile without rebinding Telegram.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->profile_intro)->toBe('Updated profile without rebinding Telegram.')
        ->and($user->telegram_user_id)->toBe('123456789')
        ->and($user->telegram_chat_id)->toBe('123456789')
        ->and($user->telegram_username)->toBe('verified_operator')
        ->and($user->password)->toBe($passwordHash);

    Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->assertSet('data.telegram_user_id', '123456789');
});

it('normalizes edited bindings and never accepts a forged verified chat ID', function (): void {
    $user = User::factory()->create(['role' => 'support', 'telegram_user_id' => '111222333']);
    $user->update(['telegram_chat_id' => '111222333', 'telegram_username' => 'verified_operator']);
    $passwordHash = $user->password;

    Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->fillForm([
            'telegram_user_id' => ' 123456789 ',
            'telegram_chat_id' => '987654321',
            'telegram_username' => 'forged_username',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->telegram_user_id)->toBe('123456789')
        ->and($user->telegram_chat_id)->toBeNull()
        ->and($user->telegram_username)->toBeNull()
        ->and($user->password)->toBe($passwordHash);

    Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->assertSet('data.telegram_user_id', '123456789')
        ->fillForm(['telegram_user_id' => '   '])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->telegram_user_id)->toBeNull()
        ->and($user->telegram_chat_id)->toBeNull()
        ->and($user->password)->toBe($passwordHash);
});

it('keeps the password and authenticated session when binding its own account without a new password', function (?string $password): void {
    $user = auth()->user();
    $passwordHash = $user->password;
    $this->withSession(['telegram_binding_marker' => 'still-authenticated'])
        ->get(AdminUserResource::getUrl('edit', ['record' => $user]))
        ->assertOk();
    $sessionPasswordHash = session('password_hash_web');

    $page = Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()]);
    expect($page->get('data.password'))->toBeIn([null, ''])
        ->and(json_encode($page->snapshot))->not->toContain($passwordHash);

    $page->fillForm(['telegram_user_id' => '123456789', ...($password === null ? [] : ['password' => $password])])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->password)->toBe($passwordHash)
        ->and($user->fresh()->telegram_user_id)->toBe('123456789')
        ->and($user->fresh()->telegram_chat_id)->toBeNull()
        ->and(json_encode($page->snapshot))->not->toContain($passwordHash);
    $this->assertAuthenticatedAs($user);
    $this->get(AdminUserResource::getUrl())
        ->assertOk()
        ->assertSessionHas('telegram_binding_marker', 'still-authenticated')
        ->assertSessionHas('password_hash_web', $sessionPasswordHash);
})->with(['empty password' => '', 'untouched password' => null]);

it('never hydrates an existing password hash into the editable password field', function (): void {
    $user = User::factory()->create(['role' => 'support']);
    $page = Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()]);

    $page->instance()->form->fill([...$page->get('data'), 'password' => $user->password]);

    expect($page->instance()->data['password'])->toBe('')
        ->and(json_encode($page->snapshot))->not->toContain($user->password);
});

it('requires a valid new password only when creating or explicitly changing it', function (): void {
    Livewire::test(CreateAdminUser::class)
        ->fillForm([
            'name' => 'New Operator', 'public_id' => 'staff_new_operator',
            'email' => 'new-operator@example.test', 'password' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'required']);

    $user = User::factory()->create(['role' => 'support']);
    $oldHash = $user->password;
    $page = Livewire::test(EditAdminUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['password' => 'short'])
        ->call('save')
        ->assertHasFormErrors(['password' => 'min']);

    expect($user->refresh()->password)->toBe($oldHash);

    $page->fillForm(['password' => 'replacement-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->password)->not->toBe($oldHash)
        ->and(Hash::check('replacement-password', $user->password))->toBeTrue();
});

it('does not let other roles edit administrator Telegram bindings', function (string $role): void {
    $admin = auth()->user();
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(AdminUserResource::getUrl('edit', ['record' => $admin]))->assertForbidden();
    Livewire::test(EditAdminUser::class, ['record' => $admin->getRouteKey()])->assertForbidden();

    expect($admin->refresh()->telegram_user_id)->toBeNull();
    Http::assertNothingSent();
})->with(['operator', 'finance', 'warehouse', 'sales', 'purchasing', 'support', 'customer']);
