<?php

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminDashboardCache;
use App\Support\DashboardSalesRange;
use Illuminate\Support\Facades\Cache;

it('builds zero filled thirty day sales metrics from fulfilled orders only', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'DASH-PAID-1',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 12000,
        'discount_cents' => 0,
        'total_cents' => 12000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now()->subDays(2)->setTime(10, 0),
        'fulfilled_at' => now()->subDays(2)->setTime(10, 30),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'DASH-PAID-2',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 8000,
        'discount_cents' => 0,
        'total_cents' => 8000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now()->subDays(2)->setTime(14, 0),
        'fulfilled_at' => now()->subDays(2)->setTime(14, 30),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'DASH-PAID-3',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 10000,
        'discount_cents' => 0,
        'total_cents' => 10000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now()->setTime(9, 0),
        'fulfilled_at' => now()->setTime(9, 30),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'DASH-PAID-NOT-FULFILLED',
        'status' => Order::STATUS_PAID,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 88000,
        'discount_cents' => 0,
        'total_cents' => 88000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'paid_at' => now()->setTime(10, 0),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'DASH-PENDING',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 99000,
        'discount_cents' => 0,
        'total_cents' => 99000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
    ]);

    $range = app(DashboardSalesRange::class);
    $daily = $range->daily();
    $summary = $range->summary();
    $twoDaysAgo = $daily->firstWhere('date', now()->subDays(2)->toDateString());

    expect($daily)->toHaveCount(30)
        ->and($twoDaysAgo)->toMatchArray([
            'sales_cents' => 20000,
            'order_count' => 2,
            'completed_order_count' => 2,
            'paid_cents' => 20000,
            'paid_order_count' => 2,
        ])
        ->and($summary)->toMatchArray([
            'total_cents' => 30000,
            'order_count' => 3,
            'created_order_count' => 5,
            'completed_order_count' => 3,
            'paid_order_count' => 4,
            'paid_cents' => 118000,
            'average_order_cents' => 10000,
            'best_day_cents' => 20000,
        ]);
});

it('renders the thirty day dashboard sales widgets', function (): void {
    $this->seed();

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('shop-daily-sales-chart', false)
        ->assertSee('shop-daily-sales-svg', false)
        ->assertSee('客户排行')
        ->assertSee('商品销售排行');
});

it('excludes cancelled and user deleted orders from pending payment review stats', function (): void {
    Cache::flush();

    $customer = User::factory()->create(['role' => 'customer']);

    $base = [
        'user_id' => $customer->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_SUBMITTED,
        'subtotal_cents' => 1000,
        'discount_cents' => 0,
        'total_cents' => 1000,
        'contact_name' => 'Buyer',
        'contact_phone' => '13800000000',
        'payment_submitted_at' => now(),
    ];

    Order::query()->create(array_merge($base, ['order_number' => 'DASH-REVIEW-ACTIVE']));
    Order::query()->create(array_merge($base, [
        'order_number' => 'DASH-REVIEW-CANCELLED',
        'status' => Order::STATUS_CANCELLED,
        'cancelled_at' => now(),
    ]));
    Order::query()->create(array_merge($base, [
        'order_number' => 'DASH-REVIEW-DELETED',
        'user_deleted_at' => now(),
    ]));

    $cache = app(AdminDashboardCache::class);

    expect(Order::query()->awaitingPaymentReview()->pluck('order_number')->all())
        ->toBe(['DASH-REVIEW-ACTIVE'])
        ->and($cache->dashboardStats()['pending_payments'])->toBe(1)
        ->and($cache->orderStats()['pending_payment_proofs'])->toBe(1)
        ->and(OrderResource::getNavigationBadge())->toBe('1');
});
