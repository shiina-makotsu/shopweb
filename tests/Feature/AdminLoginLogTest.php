<?php

use App\Models\AdminLoginLog;
use App\Models\User;

it('records login attempts for backoffice users only', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin-login@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);
    User::factory()->create([
        'email' => 'customer-login@example.com',
        'password' => 'password',
        'role' => 'customer',
    ]);

    $this->post('/login', [
        'email' => 'admin-login@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->post('/logout');

    $this->post('/login', [
        'email' => 'admin-login@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->post('/login', [
        'email' => 'customer-login@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertDatabaseHas('admin_login_logs', [
        'user_id' => $admin->id,
        'email' => 'admin-login@example.com',
        'role' => 'admin',
        'successful' => true,
    ]);
    $this->assertDatabaseHas('admin_login_logs', [
        'user_id' => $admin->id,
        'email' => 'admin-login@example.com',
        'role' => 'admin',
        'successful' => false,
        'failure_reason' => 'invalid_credentials',
    ]);

    expect(AdminLoginLog::query()->count())->toBe(2);
});

it('shows login logs to authorized admins', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $finance = User::factory()->create(['role' => 'finance']);
    $operator = User::factory()->create(['role' => 'operator']);

    AdminLoginLog::query()->create([
        'user_id' => $admin->id,
        'email' => $admin->email,
        'role' => 'admin',
        'successful' => true,
    ]);

    $this->actingAs($admin)
        ->get('/admin/admin-login-logs')
        ->assertOk()
        ->assertSee($admin->email);

    $this->actingAs($finance)
        ->get('/admin/admin-login-logs')
        ->assertOk();

    $this->actingAs($operator)
        ->get('/admin/admin-login-logs')
        ->assertForbidden();
});
