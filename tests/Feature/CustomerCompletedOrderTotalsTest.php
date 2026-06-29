<?php

use App\Filament\Resources\CustomerResource;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;

it('counts customer totals from fulfilled orders only', function (): void {
    $customer = User::factory()->create(['role' => 'customer']);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'CUS-FULFILLED',
        'status' => Order::STATUS_FULFILLED,
        'payment_status' => Order::PAYMENT_CONFIRMED,
        'subtotal_cents' => 12000,
        'discount_cents' => 0,
        'total_cents' => 12000,
        'contact_name' => 'Done',
        'contact_phone' => '10086',
        'fulfilled_at' => now(),
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'CUS-PENDING',
        'status' => Order::STATUS_PENDING_PAYMENT,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 99000,
        'discount_cents' => 0,
        'total_cents' => 99000,
        'contact_name' => 'Pending',
        'contact_phone' => '10086',
    ]);

    Order::query()->create([
        'user_id' => $customer->id,
        'order_number' => 'CUS-CANCELLED',
        'status' => Order::STATUS_CANCELLED,
        'payment_status' => Order::PAYMENT_PENDING,
        'subtotal_cents' => 88000,
        'discount_cents' => 0,
        'total_cents' => 88000,
        'contact_name' => 'Cancelled',
        'contact_phone' => '10086',
    ]);

    $row = CustomerResource::getEloquentQuery()->whereKey($customer->id)->first();

    expect($row->completed_orders_count)->toBe(1)
        ->and((int) $row->completed_orders_total_cents)->toBe(12000)
        ->and(Money::format((int) $row->completed_orders_total_cents))->toContain('120');
});
