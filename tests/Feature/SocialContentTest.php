<?php

use App\Models\Announcement;
use App\Models\ForumActivityLog;
use App\Models\ForumSection;
use App\Models\MediaAsset;
use App\Models\Category;
use App\Models\PrivateMessage;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductVariant;
use App\Models\ReferralRewardRule;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Models\SiteSetting;
use App\Models\SupportQuickReply;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\ForumThreadTemplate;
use App\Support\Url;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders announcements and marks them read when viewed', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $announcement = Announcement::query()->create([
        'title' => '维护公告',
        'slug' => 'maintenance-notice',
        'body' => '今晚维护。',
        'is_published' => true,
        'is_pinned' => true,
        'popup_when_unread' => true,
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('announcements.index'))
        ->assertOk()
        ->assertSee('维护公告')
        ->assertSee('未读');

    $this->actingAs($user)
        ->get(route('announcements.show', $announcement))
        ->assertOk()
        ->assertSee('今晚维护');

    $this->assertDatabaseHas('announcement_reads', [
        'announcement_id' => $announcement->id,
        'user_id' => $user->id,
    ]);
});

it('lets authenticated users comment on products with images disabled from backend setting', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $category = Category::query()->create(['name' => '测试分类', 'slug' => 'test']);
    $product = Product::query()->create([
        'category_id' => $category->id,
        'title' => '评论商品',
        'slug' => 'comment-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
        'comments_enabled' => true,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'COMMENT-1',
        'price_cents' => 1000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('product-comments.store', $product), [
            'rating' => 5,
            'body' => '很好用。',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_comments', [
        'product_id' => $product->id,
        'user_id' => $user->id,
        'rating' => 5,
        'body' => '很好用。',
    ]);

    $product->update(['comments_enabled' => false]);

    $this->actingAs($user)
        ->post(route('product-comments.store', $product), [
            'rating' => 5,
            'body' => '关闭后不能发。',
        ])
        ->assertNotFound();
});

it('lets users create forum threads and replies', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => '综合讨论',
        'slug' => 'general',
        'is_active' => true,
    ]);
    ReferralRewardRule::query()->create([
        'name' => 'Forum post reward',
        'trigger_events' => [ReferralRewardRule::EVENT_FORUM_THREAD_CREATED],
        'wallet_amount_cents' => 150,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => '第一帖',
            'body' => '大家好。',
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    $this->actingAs($user)
        ->post(route('forum.comments.store', [$section, $thread]), [
            'body' => '欢迎。',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('forum_threads', [
        'forum_section_id' => $section->id,
        'user_id' => $user->id,
        'title' => '第一帖',
    ]);
    $this->assertDatabaseHas('forum_comments', [
        'forum_thread_id' => $thread->id,
        'user_id' => $user->id,
        'body' => '欢迎。',
    ]);
    $this->assertDatabaseHas('wallet_transactions', [
        'user_id' => $user->id,
        'amount_cents' => 150,
        'source' => WalletTransaction::SOURCE_EVENT_REWARD,
    ]);
});

it('renders forum markdown bodies after publishing', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $viewer = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => 'Markdown Section',
        'slug' => 'markdown-section',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => 'Markdown Thread',
            'body' => "**加粗标题**\n\n- 第一项",
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    $this->actingAs($user)
        ->post(route('forum.comments.store', [$section, $thread]), [
            'body' => '[链接](https://example.com)',
        ])
        ->assertRedirect();

    $this->actingAs($viewer)
        ->get(route('forum.threads.show', [$section, $thread]))
        ->assertOk()
        ->assertSee('<strong>加粗标题</strong>', false)
        ->assertSee('<li>第一项</li>', false)
        ->assertSee('<a href="https://example.com">链接</a>', false)
        ->assertDontSee('**加粗标题**')
        ->assertDontSee('[链接](https://example.com)');
});

it('keeps the floating cart away from forum publishing actions', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    ForumSection::query()->create([
        'name' => 'Forum Layout',
        'slug' => 'forum-layout',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSee('id="site-cart-target"', false);
});

it('lets users create typed forum threads with rich template attachments', function (): void {
    Storage::fake('public_uploads');

    $user = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => '合租招租',
        'slug' => 'rent',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => '寻找合租人',
            'template' => ForumThreadTemplate::ROOMMATE,
            'body' => ForumThreadTemplate::defaultBody(ForumThreadTemplate::ROOMMATE),
            'attachments' => [
                UploadedFile::fake()->image('room.jpg'),
                UploadedFile::fake()->create('tour.mp4', 256, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    expect($thread->template)->toBe(ForumThreadTemplate::ROOMMATE)
        ->and($thread->attachment_paths)->toHaveCount(2)
        ->and($thread->body)->toContain('城市');

    $this->assertDatabaseHas('media_assets', [
        'usage' => MediaAsset::USAGE_FORUM,
        'library' => MediaAsset::LIBRARY_FORUM_USER,
        'uploaded_by_id' => $user->id,
        'mime_type' => 'video/mp4',
    ]);
});

it('renders large auto growing forum thread editors for templates and edits', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => 'Long Form Posts',
        'slug' => 'long-form-posts',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('forum.sections.threads.create', [
            'section' => $section,
            'template' => ForumThreadTemplate::MATCHMAKING,
        ]))
        ->assertOk()
        ->assertSee('data-thread-body', false)
        ->assertSee('data-auto-grow-textarea', false)
        ->assertSee('rows="24"', false)
        ->assertSee('data-min-height="544"', false);

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => 'Long editor thread',
            'template' => ForumThreadTemplate::MATCHMAKING,
            'body' => ForumThreadTemplate::defaultBody(ForumThreadTemplate::MATCHMAKING),
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    $this->actingAs($user)
        ->get(route('forum.threads.show', [$section, $thread]))
        ->assertOk()
        ->assertSee('data-auto-grow-textarea', false)
        ->assertSee('rows="18"', false)
        ->assertSee('data-min-height="416"', false);
});

it('renders forum thread pages for users created before public ids existed', function (): void {
    $user = User::factory()->create(['role' => 'customer', 'public_id' => null]);
    $section = ForumSection::query()->create([
        'name' => 'Legacy Users',
        'slug' => 'legacy-users',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => 'Legacy thread',
            'body' => 'This thread page should not return 500.',
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    $this->actingAs($user)
        ->get(route('forum.threads.show', [$section, $thread]))
        ->assertOk()
        ->assertSee('Legacy thread');

    expect($user->fresh()->public_id)->not->toBeNull();
});

it('lets thread owners and section moderators manage forum content with activity logs', function (): void {
    Storage::fake('public_uploads');

    $owner = User::factory()->create(['role' => 'customer', 'public_id' => 'owner_1']);
    $moderator = User::factory()->create(['role' => 'customer', 'public_id' => 'mod_1']);
    $other = User::factory()->create(['role' => 'customer', 'public_id' => 'other_1']);
    $section = ForumSection::query()->create([
        'name' => '综合讨论',
        'slug' => 'general',
        'is_active' => true,
    ]);
    $section->moderators()->attach($moderator);
    $moderator->update(['forum_role' => 'moderator']);

    $this->actingAs($owner)
        ->post(route('forum.threads.store', $section), [
            'title' => '带附件的帖子',
            'body' => '大家好',
            'attachments' => [
                UploadedFile::fake()->image('topic.png'),
            ],
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();

    expect($thread->attachment_paths)->toHaveCount(1);
    $this->assertDatabaseHas('media_assets', [
        'usage' => MediaAsset::USAGE_FORUM,
        'library' => MediaAsset::LIBRARY_FORUM_USER,
        'uploaded_by_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->patch(route('forum.threads.update', [$section, $thread]), [
            'title' => '贴主修改标题',
            'body' => '更新正文',
        ])
        ->assertRedirect();

    $this->actingAs($other)
        ->post(route('forum.comments.store', [$section, $thread]), [
            'body' => '普通回复',
        ])
        ->assertRedirect();

    $comment = $thread->comments()->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('forum.comments.destroy', [$section, $thread, $comment]))
        ->assertRedirect();

    $this->actingAs($moderator)
        ->post(route('forum.threads.pin', [$section, $thread]))
        ->assertRedirect();

    $this->actingAs($other)
        ->post(route('forum.threads.pin', [$section, $thread]))
        ->assertForbidden();

    expect($thread->fresh()->is_pinned)->toBeTrue()
        ->and($moderator->fresh()->forum_role)->toBe('moderator');

    $this->assertDatabaseHas('forum_activity_logs', [
        'forum_thread_id' => $thread->id,
        'actor_user_id' => $owner->id,
        'action' => 'thread_updated',
    ]);
    $this->assertDatabaseHas('forum_activity_logs', [
        'forum_comment_id' => $comment->id,
        'actor_user_id' => $owner->id,
        'action' => 'comment_deleted',
    ]);
    $this->assertDatabaseHas('forum_activity_logs', [
        'forum_thread_id' => $thread->id,
        'actor_user_id' => $moderator->id,
        'action' => 'thread_pinned',
    ]);

    expect(ForumActivityLog::query()->count())->toBeGreaterThanOrEqual(4);
});

it('lets guests create support tickets with a generated guest id', function (): void {
    $this->get(route('support.demands'))
        ->assertOk()
        ->assertSee('客服工单');

    $this->post(route('support.store'), [
        'category' => 'consultation',
        'subject' => '无法登录',
        'message' => '我忘记密码了。',
        'guest_email' => 'guest@example.com',
    ])
        ->assertRedirect();

    $ticket = SupportTicket::query()->firstOrFail();

    expect($ticket->user_id)->toBeNull()
        ->and($ticket->guest_id)->toStartWith('guest_')
        ->and($ticket->guest_email)->toBe('guest@example.com');
});

it('supports chat style customer service sessions with attachments and admin reception', function (): void {
    Storage::fake('support_attachments');

    $admin = User::factory()->create(['role' => 'support', 'name' => '客服甲']);

    $this->get(route('support.index'))
        ->assertOk()
        ->assertSee('客服会话')
        ->assertSee('正在排队等待客服接入')
        ->assertSee('最长等待约 10 分钟')
        ->assertSee('暂无消息');

    $this->post(route('support.messages.store'), [
        'message' => '我需要即时帮助。',
        'guest_email' => 'guest-chat@example.com',
        'attachment' => UploadedFile::fake()->image('chat.png', 160, 120),
    ])->assertRedirect();

    $session = SupportChatSession::query()->firstOrFail();
    $message = $session->messages()->firstOrFail();

    expect($session->guest_id)->toStartWith('guest_')
        ->and($session->guest_email)->toBe('guest-chat@example.com')
        ->and($message->attachment_path)->not->toBeNull();

    Storage::disk('support_attachments')->assertExists($message->attachment_path);

    $this->get(route('support.messages.attachment', $message))
        ->assertOk();

    app(\App\Services\SupportChatService::class)->reply($session, $admin, '这里是客服回复。');

    expect($message->fresh()->read_at)->not->toBeNull();

    $adminAttachment = app(\App\Services\ChatAttachmentService::class)->storeSupportAttachment(
        UploadedFile::fake()->image('admin-reply.png', 120, 120),
        $session,
    );
    $adminAttachmentReply = app(\App\Services\SupportChatService::class)->reply($session->fresh(), $admin, '', $adminAttachment);

    Storage::disk('support_attachments')->assertExists($adminAttachmentReply->attachment_path);
    expect($adminAttachmentReply->body)->toBeNull()
        ->and($adminAttachmentReply->isImage())->toBeTrue();

    $adminReply = $session->messages()
        ->where('sender_type', SupportChatMessage::SENDER_ADMIN)
        ->whereNotNull('body')
        ->firstOrFail();

    expect($adminReply->read_at)->toBeNull();

    $this->get(route('support.index'))
        ->assertOk()
        ->assertSee('客服 客服甲 为您服务')
        ->assertSee('这里是客服回复。');

    expect($adminReply->fresh()->read_at)->not->toBeNull();

    $this->actingAs($admin)
        ->get('/admin/guest-support-chat-sessions')
        ->assertOk()
        ->assertSee('游客会话');

    $this->actingAs($admin)
        ->get("/admin/guest-support-chat-sessions/{$session->id}/edit")
        ->assertOk()
        ->assertSee('会话 #'.$session->id)
        ->assertSee('发送回复')
        ->assertDontSee('暂无聊天记录');

    expect($session->fresh()->assigned_admin_id)->toBe($admin->id)
        ->and($session->fresh()->status)->toBe(SupportChatSession::STATUS_ACTIVE);
});

it('keeps empty support sessions out of admin reception queues and polls new messages', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'support', 'name' => '客服乙']);

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk();

    $emptySession = SupportChatSession::query()->whereBelongsTo($user)->firstOrFail();

    $this->actingAs($admin)
        ->get('/admin/support-chat-sessions')
        ->assertOk()
        ->assertDontSee('会话 #'.$emptySession->id);

    $this->actingAs($user)
        ->post(route('support.messages.store'), [
            'support_chat_session_id' => $emptySession->id,
            'message' => '0',
        ])
        ->assertRedirect(route('support.sessions.show', $emptySession));

    expect($emptySession->messages()->latest('id')->first()->body)->toBe('0');

    $this->actingAs($admin)
        ->get('/admin/support-chat-sessions')
        ->assertOk()
        ->assertSee('客服会话')
        ->assertSee('未读会话')
        ->assertSee('接待中会话')
        ->assertSee('已结束会话');

    app(\App\Services\SupportChatService::class)->reply($emptySession->fresh(), $admin, '已收到 0。');

    $response = $this->actingAs($user)
        ->get(route('support.sessions.messages', $emptySession))
        ->assertOk()
        ->assertJsonPath('status_label', '接待中')
        ->assertJsonPath('assigned_admin', '客服乙');

    expect($response->json('html'))->toContain('已收到 0。');
});

it('splits admin support chat pagination into unread active and ended tabs', function (): void {
    $admin = User::factory()->create(['role' => 'support']);
    $user = User::factory()->create(['role' => 'customer', 'name' => '未读客户']);
    $activeUser = User::factory()->create(['role' => 'customer', 'name' => '接待中客户']);
    $endedUser = User::factory()->create(['role' => 'customer', 'name' => '已结束客户']);

    $unreadSession = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'status' => SupportChatSession::STATUS_OPEN,
        'last_message_at' => now(),
    ]);
    $unreadSession->messages()->create([
        'sender_user_id' => $user->id,
        'sender_type' => SupportChatMessage::SENDER_CUSTOMER,
        'body' => '还没有被客服读取。',
    ]);

    $activeSession = SupportChatSession::query()->create([
        'user_id' => $activeUser->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'assigned_admin_id' => $admin->id,
        'last_message_at' => now()->subMinute(),
    ]);
    $activeSession->messages()->create([
        'sender_user_id' => $activeUser->id,
        'sender_type' => SupportChatMessage::SENDER_CUSTOMER,
        'body' => '已经读取的会话。',
        'read_at' => now(),
    ]);
    $activeSession->messages()->create([
        'sender_user_id' => $admin->id,
        'sender_type' => SupportChatMessage::SENDER_ADMIN,
        'body' => '客服已经回复。',
    ]);

    $endedSession = SupportChatSession::query()->create([
        'user_id' => $endedUser->id,
        'status' => SupportChatSession::STATUS_CLOSED,
        'assigned_admin_id' => $admin->id,
        'last_message_at' => now()->subMinutes(2),
        'ended_at' => now()->subMinute(),
    ]);
    $endedSession->messages()->create([
        'sender_user_id' => $endedUser->id,
        'sender_type' => SupportChatMessage::SENDER_CUSTOMER,
        'body' => '客户关闭的会话。',
        'read_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/support-chat-sessions')
        ->assertOk()
        ->assertSee('未读会话')
        ->assertSee('接待中会话')
        ->assertSee('已结束会话')
        ->assertSee('未读客户')
        ->assertDontSee('接待中客户')
        ->assertDontSee('已结束客户');

    $this->actingAs($admin)
        ->get('/admin/support-chat-sessions?tab=active')
        ->assertOk()
        ->assertSee('接待中客户')
        ->assertDontSee('未读客户')
        ->assertDontSee('已结束客户');

    $this->actingAs($admin)
        ->get('/admin/support-chat-sessions?tab=ended')
        ->assertOk()
        ->assertSee('已结束客户')
        ->assertDontSee('未读客户')
        ->assertDontSee('接待中客户');
});

it('ignores ended support sessions when showing frontend unread badges', function (): void {
    $user = User::factory()->create(['role' => 'customer']);

    foreach ([SupportChatSession::STATUS_ENDED, SupportChatSession::STATUS_CLOSED] as $status) {
        $session = SupportChatSession::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'last_message_at' => now(),
            'ended_at' => now(),
        ]);
        $session->messages()->create([
            'sender_type' => SupportChatMessage::SENDER_ADMIN,
            'body' => 'Closed unread message',
        ]);
    }

    $this->actingAs($user);
    $provider = new \App\Providers\AppServiceProvider(app());
    $counter = new \ReflectionMethod($provider, 'supportUnreadMessageCount');
    $counter->setAccessible(true);

    expect($counter->invoke($provider))->toBe(0);

    $activeSession = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now(),
    ]);
    $activeSession->messages()->create([
        'sender_type' => SupportChatMessage::SENDER_SYSTEM,
        'body' => 'Active unread message',
    ]);

    expect($counter->invoke($provider))->toBe(1);
});

it('marks support system messages read when the customer opens a session', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $session = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now(),
    ]);
    $message = $session->messages()->create([
        'sender_type' => SupportChatMessage::SENDER_SYSTEM,
        'body' => 'System notice',
    ]);

    $this->actingAs($user)
        ->get(route('support.sessions.show', $session))
        ->assertOk();

    expect($message->fresh()->read_at)->not->toBeNull();
});

it('starts product support chats and toggles product preferences from product pages', function (): void {
    $product = Product::query()->create([
        'title' => '客服咨询商品',
        'slug' => 'support-chat-product',
        'summary' => '用于测试商品页客服入口。',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_ONLINE,
    ]);
    ProductVariant::query()->create([
        'product_id' => $product->id,
        'sku' => 'SUPPORT-PRODUCT-1',
        'price_cents' => 5200,
        'stock' => 5,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('咨询此商品')
        ->assertSee('加入愿望单')
        ->assertSee('收藏商品');

    $this->actingAs($user)
        ->post(route('products.wishlist.toggle', $product))
        ->assertRedirect();

    $this->assertDatabaseHas('product_wishlists', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->post(route('products.favorite.toggle', $product))
        ->assertRedirect();

    $this->assertDatabaseHas('product_favorites', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->post(route('support.sessions.store'), ['product_id' => $product->id])
        ->assertRedirect();

    $session = SupportChatSession::query()->whereBelongsTo($user)->firstOrFail();
    $message = $session->messages()->firstOrFail();

    expect($message->sender_type)->toBe(SupportChatMessage::SENDER_CUSTOMER)
        ->and($message->body)->toContain('商品咨询')
        ->and($message->body)->toContain($product->title);
});

it('supports multiple chat windows and closes deleted windows against later replies', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $session = SupportChatSession::query()->create([
        'user_id' => $user->id,
        'status' => SupportChatSession::STATUS_ACTIVE,
        'last_message_at' => now()->subMinutes(SupportChatSession::CUSTOMER_IDLE_MINUTES + 5),
    ]);

    $this->actingAs($user)
        ->get(route('support.index'))
        ->assertOk()
        ->assertSee('本次接待已结束');

    expect($session->fresh()->status)->toBe(SupportChatSession::STATUS_ENDED)
        ->and($session->fresh()->served_count)->toBe(1);

    $this->actingAs($user)
        ->post(route('support.messages.store'), [
            'support_chat_session_id' => $session->id,
            'message' => '继续咨询。',
        ])
        ->assertRedirect(route('support.sessions.show', $session));

    expect($session->fresh()->status)->toBe(SupportChatSession::STATUS_OPEN)
        ->and($session->messages()->latest('id')->first()->body)->toBe('继续咨询。');

    $this->actingAs($user)
        ->post(route('support.sessions.store'))
        ->assertRedirect();

    expect(SupportChatSession::query()->where('user_id', $user->id)->count())->toBe(2);

    $this->actingAs($user)
        ->delete(route('support.sessions.destroy', $session))
        ->assertRedirect(route('support.index'));

    expect($session->fresh()->deleted_by_customer_at)->not->toBeNull()
        ->and($session->fresh()->status)->toBe(SupportChatSession::STATUS_CLOSED);

    $admin = User::factory()->create(['role' => 'support']);

    expect(fn () => app(\App\Services\SupportChatService::class)->reply($session->fresh(), $admin, '不能回复。'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('lets users update their avatar and profile intro', function (): void {
    Storage::fake('public_uploads');

    $user = User::factory()->create([
        'role' => 'customer',
        'public_id' => 'profile_user',
        'name' => 'Old Name',
    ]);

    $this->actingAs($user)
        ->get(route('user.section', 'profile'))
        ->assertOk()
        ->assertSee('个人资料')
        ->assertSee('头像');

    $this->actingAs($user)
        ->patch(route('user.profile.update'), [
            'name' => 'Maple',
            'profile_intro' => '喜欢分享商品体验和论坛讨论。',
            'avatar' => UploadedFile::fake()->image('avatar.png', 160, 160),
            'avatar_cropped' => 'data:image/png;base64,'.base64_encode('cropped-avatar'),
        ])
        ->assertRedirect();

    $fresh = $user->fresh();

    expect($fresh->name)->toBe('Maple')
        ->and($fresh->profile_intro)->toBe('喜欢分享商品体验和论坛讨论。')
        ->and($fresh->avatar_path)->not->toBeNull();

    Storage::disk('public_uploads')->assertExists($fresh->avatar_path);
    expect($fresh->avatar_path)->toEndWith('.png');

    $this->actingAs($fresh)
        ->get(route('users.show', $fresh))
        ->assertOk()
        ->assertSee('Maple')
        ->assertSee('喜欢分享商品体验和论坛讨论。');
});

it('searches products and users and links to private messages', function (): void {
    Storage::fake('private_attachments');

    $viewer = User::factory()->create(['role' => 'customer', 'public_id' => 'viewer_1']);
    $target = User::factory()->create(['role' => 'customer', 'public_id' => 'alice_1', 'name' => 'Alice']);
    $category = Category::query()->create(['name' => '搜索分类', 'slug' => 'search']);
    Product::query()->create([
        'category_id' => $category->id,
        'title' => '粉蓝商品',
        'slug' => 'pink-blue-product',
        'status' => Product::STATUS_PUBLISHED,
        'fulfillment_type' => Product::FULFILLMENT_LOGISTICS,
    ]);

    $this->actingAs($viewer)
        ->get(route('search.index', ['q' => 'alice']))
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee(Url::route('messages.thread', $target), false);

    $this->actingAs($viewer)
        ->post(route('messages.store', $target), [
            'body' => '0',
            'attachment' => UploadedFile::fake()->image('hello.png', 80, 80),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('private_messages', [
        'sender_id' => $viewer->id,
        'recipient_id' => $target->id,
        'body' => '0',
    ]);

    $message = PrivateMessage::query()->firstOrFail();
    Storage::disk('private_attachments')->assertExists($message->attachment_path);

    $this->actingAs($target)
        ->get(route('messages.thread', $viewer))
        ->assertOk()
        ->assertSee('搜索聊天记录')
        ->assertSee('0')
        ->assertSee('hello.png');

    $this->actingAs($target)
        ->get(route('messages.attachment', $message))
        ->assertOk();
});

it('shows private chat in the user center with capped unread badges', function (): void {
    $viewer = User::factory()->create(['role' => 'customer', 'public_id' => 'viewer_chat', 'name' => 'Viewer']);
    $target = User::factory()->create(['role' => 'customer', 'public_id' => 'alice_chat', 'name' => 'Alice']);

    PrivateMessage::query()->create([
        'sender_id' => $viewer->id,
        'recipient_id' => $target->id,
        'body' => '我发出的消息。',
    ]);

    foreach (range(1, 105) as $index) {
        PrivateMessage::query()->create([
            'sender_id' => $target->id,
            'recipient_id' => $viewer->id,
            'body' => '未读消息 '.$index,
        ]);
    }

    $this->actingAs($viewer)
        ->get(route('user.center'))
        ->assertOk()
        ->assertSee('聊天')
        ->assertSee('99+');

    $this->actingAs($viewer)
        ->get(route('user.section', 'chat'))
        ->assertOk()
        ->assertSee('私聊会话')
        ->assertSee('Alice')
        ->assertSee('99+')
        ->assertSee(Url::route('messages.thread', $target), false);
});

it('browses forum sections before posting and supports search sorting and locked threads', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => '版块浏览',
        'slug' => 'section-browse',
        'is_active' => true,
    ]);
    foreach (range(2, 7) as $index) {
        ForumSection::query()->create([
            'name' => '版块分页 '.$index,
            'slug' => 'section-page-'.$index,
            'sort_order' => $index,
            'is_active' => true,
        ]);
    }

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('md:grid-cols-3', false)
        ->assertSee('sections_page=2', false)
        ->assertSee('版块分页 6')
        ->assertDontSee('版块分页 7');

    $this->actingAs($user)
        ->get(route('forum.index', ['sections_page' => 2]))
        ->assertOk()
        ->assertSee('版块分页 7');

    $this->actingAs($user)
        ->get(route('forum.sections.show', $section))
        ->assertOk()
        ->assertSee('帖子')
        ->assertSee('发布新帖')
        ->assertDontSee('发布</button>', false);

    $this->actingAs($user)
        ->get(route('forum.sections.threads.create', $section))
        ->assertOk()
        ->assertSee('发布新帖')
        ->assertSee('版块浏览');

    $this->actingAs($user)
        ->post(route('forum.threads.store', $section), [
            'title' => '搜索关键词帖子',
            'body' => '这里有一个独特关键词。',
        ])
        ->assertRedirect();

    $thread = $section->threads()->firstOrFail();
    $thread->update(['likes_count' => 2, 'views_count' => 10, 'is_locked' => true]);

    $this->actingAs($user)
        ->get(route('forum.index', ['q' => '独特关键词', 'sort' => 'hot']))
        ->assertOk()
        ->assertSee('搜索关键词帖子')
        ->assertSee('10 访问');

    $this->actingAs($user)
        ->post(route('forum.comments.store', [$section, $thread]), [
            'body' => '锁帖后不能回复。',
        ])
        ->assertForbidden();
});

it('keeps forum section badges until unread threads are opened', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $author = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => 'Unread Section',
        'slug' => 'unread-section',
        'is_active' => true,
    ]);

    $section->threads()->create([
        'user_id' => $author->id,
        'title' => 'Unread thread',
        'slug' => 'unread-thread',
        'body' => 'Body',
    ]);

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('aria-label="有未读帖子"', false);

    $this->actingAs($user)
        ->get(route('forum.sections.show', $section))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('aria-label="有未读帖子"', false);

    $this->actingAs($user)
        ->get(route('forum.threads.show', [$section, $section->threads()->firstOrFail()]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSee('aria-label="有未读帖子"', false);
});

it('clears an empty forum section badge only after the viewer enters the section', function (): void {
    $user = User::factory()->create(['role' => 'customer']);
    $section = ForumSection::query()->create([
        'name' => 'Empty Unread Section',
        'slug' => 'empty-unread-section',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertSee('aria-label="有未读帖子"', false);

    $this->actingAs($user)
        ->get(route('forum.sections.show', $section))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('forum.index'))
        ->assertOk()
        ->assertDontSee('aria-label="有未读帖子"', false);
});

it('adds support ai comfort messages when an open chat waits too long', function (): void {
    SiteSetting::query()->updateOrCreate(['id' => 1], [
        'site_name' => 'ShopWeb',
        'support_ai_enabled' => true,
        'support_ai_idle_minutes' => 1,
        'support_ai_system_prompt' => '我先陪你等客服接入。',
    ]);

    $session = SupportChatSession::query()->create([
        'guest_id' => 'guest_idle_ai',
        'status' => SupportChatSession::STATUS_OPEN,
        'last_message_at' => now()->subMinutes(5),
    ]);

    $this->withSession(['support_guest_id' => 'guest_idle_ai'])
        ->get(route('support.sessions.show', $session))
        ->assertOk()
        ->assertSee('最长等待约 1 分钟')
        ->assertSee('我先陪你等客服接入。');

    $this->assertDatabaseHas('support_chat_messages', [
        'support_chat_session_id' => $session->id,
        'sender_type' => \App\Models\SupportChatMessage::SENDER_SYSTEM,
        'body' => '我先陪你等客服接入。',
    ]);
});

it('matches support quick replies by keyword and regex rules', function (): void {
    SupportQuickReply::query()->create([
        'title' => '退款自动回复',
        'body' => '请先提供订单号，我来帮你核对。',
        'match_pattern' => "退款\n退货",
        'match_mode' => SupportQuickReply::MATCH_KEYWORD,
        'trigger_action' => SupportQuickReply::ACTION_REPLY,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    SupportQuickReply::query()->create([
        'title' => '人工接待提醒',
        'body' => '已提醒客服尽快接待。',
        'match_pattern' => '/(人工|客服)/',
        'match_mode' => SupportQuickReply::MATCH_REGEX,
        'trigger_action' => SupportQuickReply::ACTION_NOTIFY_STAFF,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $guestId = 'guest_quick_reply';

    $this->withSession(['support_guest_id' => $guestId])
        ->post(route('support.messages.store'), [
            'message' => '我想退款',
        ])
        ->assertRedirect();

    $session = SupportChatSession::query()->where('guest_id', $guestId)->firstOrFail();

    expect($session->messages()->where('sender_type', SupportChatMessage::SENDER_SYSTEM)->exists())->toBeTrue();
    expect($session->messages()->where('sender_type', SupportChatMessage::SENDER_SYSTEM)->latest()->first()->body)
        ->toContain('请先提供订单号');

    $this->withSession(['support_guest_id' => $guestId])
        ->post(route('support.messages.store'), [
            'support_chat_session_id' => $session->id,
            'message' => '麻烦人工处理一下',
        ])
        ->assertRedirect();

    expect($session->fresh()->messages()->where('sender_type', SupportChatMessage::SENDER_SYSTEM)->count())->toBeGreaterThanOrEqual(2)
        ->and($session->fresh()->messages()->where('sender_type', SupportChatMessage::SENDER_SYSTEM)->where('body', 'like', '%已提醒客服尽快接待%')->exists())
        ->toBeTrue();
});
