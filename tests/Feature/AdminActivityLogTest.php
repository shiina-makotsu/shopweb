<?php

use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderService;

it('records admin activity for order payment and cancellation actions', function (): void {
    $this->seed();

    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $variant = ProductVariant::query()->firstOrFail();

    $this->post(route('cart.items.store'), [
        'variant_id' => $variant->id,
        'quantity' => 1,
    ]);

    $this->actingAs($customer)->post(route('checkout.store'), [
        'contact_name' => '测试客户',
        'contact_phone' => '13800000000',
        'contact_email' => 'buyer@example.com',
    ]);

    $order = Order::query()->where('user_id', $customer->id)->firstOrFail();
    $orders = app(OrderService::class);

    $orders->confirmPayment($order, $admin);
    $orders->cancel($order->fresh(), $admin, '客户申请取消');

    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'order_payment_confirmed',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'description' => $order->order_number,
    ]);
    $this->assertDatabaseHas('admin_activity_logs', [
        'user_id' => $admin->id,
        'action' => 'order_cancelled',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'description' => $order->order_number,
    ]);

    expect(AdminActivityLog::query()->count())->toBe(2);
});

it('shows readable admin activity logs in the admin panel', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    AdminActivityLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'products_csv_imported',
        'description' => '商品/SKU CSV 导入',
        'properties' => ['result' => ['processed' => 1, 'created' => 1]],
    ]);

    $this->actingAs($admin)
        ->get('/admin/admin-activity-logs')
        ->assertOk()
        ->assertSee('导入商品/SKU CSV')
        ->assertSee('结果：处理数量：1、新增数量：1');
});
