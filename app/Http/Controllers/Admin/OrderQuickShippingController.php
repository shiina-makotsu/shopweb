<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Services\AdminActivityLogger;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OrderQuickShippingController extends Controller
{
    public function update(Request $request, Order $order, AdminActivityLogger $activity, OrderService $orders): RedirectResponse
    {
        if (! $this->ensureShippingSchema()) {
            return back()->withErrors([
                'shipping' => '物流字段自动补齐失败，请先在服务器执行 php artisan migrate 后重试。',
            ]);
        }

        $data = $request->validate([
            'shipping_carrier_id' => ['nullable', 'integer', 'exists:shipping_carriers,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $carrier = filled($data['shipping_carrier_id'] ?? null)
            ? ShippingCarrier::query()->find((int) $data['shipping_carrier_id'])
            : null;
        $trackingNumber = trim((string) ($data['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($data['tracking_url'] ?? ''));

        if ($trackingUrl === '' && $carrier) {
            $trackingUrl = (string) ($carrier->trackingUrl($trackingNumber) ?? '');
        }

        $changes = [];
        $updates = [
            'shipping_carrier_id' => $carrier?->id,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'tracking_url' => $trackingUrl !== '' ? $trackingUrl : null,
        ];

        foreach ($updates as $field => $newValue) {
            $oldValue = $order->getAttribute($field);

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        $shouldShip = $trackingNumber !== ''
            && in_array($order->status, [
                Order::STATUS_PAID,
                Order::STATUS_PENDING_SHIPMENT,
                Order::STATUS_INCOMING,
            ], true);

        if ($changes !== []) {
            $order->update($updates);

            $activity->log('order_quick_shipping_updated', $order->fresh(), trim((string) ($data['admin_note'] ?? '')) ?: '后台列表补充物流信息', [
                'order_number' => $order->order_number,
                'note' => trim((string) ($data['admin_note'] ?? '')) ?: '后台列表补充物流信息',
                'changes' => $changes,
            ], $request->user());
        }

        if ($shouldShip) {
            $orders->markAwaitingReceiptFromLogistics($order->fresh() ?? $order, $updates, $request->user());
        }

        return back();
    }

    private function ensureShippingSchema(): bool
    {
        try {
            if (! Schema::hasTable('shipping_carriers')) {
                Schema::create('shipping_carriers', function (Blueprint $table): void {
                    $table->id();
                    $table->string('name');
                    $table->string('code')->unique();
                    $table->string('tracking_url_template')->nullable();
                    $table->string('api_endpoint')->nullable();
                    $table->text('api_notes')->nullable();
                    $table->boolean('is_international')->default(false)->index();
                    $table->boolean('is_active')->default(true)->index();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();
                });
            }

            if (! Schema::hasColumn('orders', 'shipping_carrier_id')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unsignedBigInteger('shipping_carrier_id')->nullable()->index();
                });
            }

            if (! Schema::hasColumn('orders', 'tracking_number')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->string('tracking_number')->nullable();
                });
            }

            if (! Schema::hasColumn('orders', 'tracking_url')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->string('tracking_url')->nullable();
                });
            }

            if (! Schema::hasColumn('orders', 'shipped_at')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->timestamp('shipped_at')->nullable();
                });
            }

            if (! Schema::hasColumn('orders', 'delivered_at')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->timestamp('delivered_at')->nullable();
                });
            }

            return Schema::hasTable('shipping_carriers')
                && Schema::hasColumn('orders', 'shipping_carrier_id')
                && Schema::hasColumn('orders', 'tracking_number')
                && Schema::hasColumn('orders', 'tracking_url')
                && Schema::hasColumn('orders', 'shipped_at')
                && Schema::hasColumn('orders', 'delivered_at');
        } catch (Throwable $exception) {
            Log::error('Order quick shipping schema repair failed.', ['exception' => $exception]);

            return false;
        }
    }
}
