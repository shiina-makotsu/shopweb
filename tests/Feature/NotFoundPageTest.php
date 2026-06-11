<?php

use App\Models\MediaAsset;
use App\Models\Page;

it('renders the default storefront 404 page with a home link', function (): void {
    $this
        ->get('/missing-page')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('页面不存在')
        ->assertSee('回到首页')
        ->assertSee('href="/"', false);
});

it('renders custom 404 content from the published custom page with slug 404', function (): void {
    $cover = MediaAsset::query()->create([
        'name' => '404 cover',
        'path' => 'errors/not-found.jpg',
        'disk' => 'public_uploads',
        'mime_type' => 'image/jpeg',
        'alt' => 'Custom not found image',
    ]);

    Page::query()->create([
        'title' => '自定义迷路页面',
        'slug' => '404',
        'cover_media_asset_id' => $cover->id,
        'excerpt' => '这是一段自定义 404 摘要',
        'body' => "## 页面走丢了\n\n请从首页重新开始。",
        'is_published' => true,
    ]);

    $this
        ->get('/missing-custom-page')
        ->assertNotFound()
        ->assertSee('自定义迷路页面')
        ->assertSee('/uploads/errors/not-found.jpg', false)
        ->assertSee('Custom not found image', false)
        ->assertSee('页面走丢了')
        ->assertSee('请从首页重新开始。')
        ->assertSee('href="/"', false);
});
