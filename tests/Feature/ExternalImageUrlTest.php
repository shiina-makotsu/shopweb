<?php

use App\Filament\Resources\FriendLinkResource;
use App\Filament\Resources\MediaAssetResource;
use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\SiteSetting;
use App\Models\User;

it('allows image fields to use external web urls', function (): void {
    $asset = MediaAsset::createImageFromUploadOrUrl([
        'external_url' => 'https://cdn.example.test/logo.png',
        'name' => 'Remote Logo',
    ], MediaAsset::USAGE_LOGO);

    expect($asset->disk)->toBe('external')
        ->and($asset->path)->toBe('https://cdn.example.test/logo.png')
        ->and($asset->url())->toBe('https://cdn.example.test/logo.png')
        ->and($asset->isImage())->toBeTrue();

    $setting = SiteSetting::query()->create([
        'site_name' => 'ShopWeb',
        'logo_path' => 'https://cdn.example.test/logo.png',
        'payment_qr_path' => 'https://cdn.example.test/pay.png',
    ]);

    expect($setting->logoUrl())->toBe('https://cdn.example.test/logo.png')
        ->and($setting->paymentQrUrl())->toBe('https://cdn.example.test/pay.png');

    $link = FriendLink::query()->create([
        'site_name' => 'Friend',
        'url' => 'https://example.test',
        'image_path' => 'https://cdn.example.test/friend.webp',
        'is_active' => true,
    ]);

    expect($link->imageUrl())->toBe('https://cdn.example.test/friend.webp');
});

it('prefers uploaded local image paths over stale external urls', function (): void {
    $asset = MediaAsset::createImageFromUploadOrUrl([
        'path' => 'media/local-logo.png',
        'external_url' => 'https://cdn.example.test/old-logo.png',
        'name' => 'Local Logo',
    ], MediaAsset::USAGE_LOGO);

    expect($asset->path)->toBe('media/local-logo.png')
        ->and($asset->disk)->toBe('public_uploads');

    $mediaData = MediaAssetResource::normalizeAssetFormData([
        'path' => 'media/new-logo.png',
        'external_url' => 'https://cdn.example.test/old-logo.png',
    ]);

    expect($mediaData['path'])->toBe('media/new-logo.png')
        ->and($mediaData['disk'])->toBe('public_uploads');

    $friendData = FriendLinkResource::normalizeImageFormData([
        'image_path' => 'friend-links/new.png',
        'image_url' => 'https://cdn.example.test/old-friend.png',
    ]);

    expect($friendData['image_path'])->toBe('friend-links/new.png')
        ->and($friendData)->not->toHaveKey('image_url');
});

it('renders friend link image url preview controls in the admin form', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/friend-links/create')
        ->assertOk()
        ->assertSee('image_url_preview', false)
        ->assertSee('粘贴图片 URL 或上传图片后显示预览。');
});
