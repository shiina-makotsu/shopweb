<?php

use App\Models\Announcement;
use App\Models\ForumActivityLog;
use App\Models\ForumSection;
use App\Models\MediaAsset;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductVariant;
use App\Models\SupportTicket;
use App\Models\User;
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

it('searches products and users and links to private messages', function (): void {
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
        ->assertSee(route('messages.thread', $target), false);

    $this->actingAs($viewer)
        ->post(route('messages.store', $target), ['body' => '你好。'])
        ->assertRedirect();

    $this->assertDatabaseHas('private_messages', [
        'sender_id' => $viewer->id,
        'recipient_id' => $target->id,
        'body' => '你好。',
    ]);
});
