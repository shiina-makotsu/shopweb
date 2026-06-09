<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

it('allows admins to access backup downloads', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/backups')
        ->assertOk()
        ->assertSee('数据库备份')
        ->assertSee(route('admin.backups.database'), false)
        ->assertSee(route('admin.backups.uploads'), false);
});

it('blocks customers from backup downloads', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get('/admin/backups')
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('admin.backups.database'))
        ->assertForbidden();
});

it('downloads public uploads as a zip archive', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    try {
        File::ensureDirectoryExists(public_path('uploads/test-backup'));
        File::put(public_path('uploads/test-backup/readme.txt'), 'backup test');

        $this->actingAs($admin)
            ->get(route('admin.backups.uploads'))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    } finally {
        File::deleteDirectory(public_path('uploads/test-backup'));
        File::deleteDirectory(storage_path('app/private/backups'));
    }
});
