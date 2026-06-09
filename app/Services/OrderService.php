<?php

namespace App\Services;

use App\Mail\OrderShippedMail;
use App\Models\CouponRedemption;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCarrier;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CouponService $coupons,
        private readonly AdminActivityLogger $activity,
    ) {}

    public function createFromCart(User $user, array $data): Order
    {
        $cartItems = $this->cart->items();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => '购物车为空。']);
        }

        return DB::transaction(function () use ($user, $data, $cartItems): Order {
            $subtotalCents = (int) $cartItems->sum('line_total_cents');
            $coupon = $this->coupons->resolve($data['coupon_code'] ?? null, $user, $subtotalCents, $cartItems);
            $discountCents = $coupon?->discountFor($subtotalCents) ?? 0;
            $totalCents = max(0, $subtotalCents - $discountCents);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->nextOrderNumber(),
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => Order::PAYMENT_PENDING,
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => $discountCents,
                'total_cents' => $totalCents,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'] ?? null,
                'requires_shipping' => (bool) ($data['requires_shipping'] ?? false),
                'shipping_address' => $data['shipping_address'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            foreach ($cartItems as $cartItem) {
                /** @var ProductVariant $variant */
                $variant = ProductVariant::query()
                    ->whereKey($cartItem['variant']->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $product = $cartItem['product'];

                if (! $variant->is_active || ($product->status === Product::STATUS_PUBLISHED && $variant->stock < $cartItem['quantity'])) {
                    throw ValidationException::withMessages([
                        'cart' => "SKU {$variant->sku} 库存不足。",
                    ]);
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_title' => $product->title,
                    'product_status' => $product->status,
                    'variant_sku' => $variant->sku,
                    'variant_specs' => $variant->specs,
                    'unit_price_cents' => $variant->effectivePriceCents(),
                    'quantity' => $cartItem['quantity'],
                    'line_total_cents' => $variant->effectivePriceCents() * $cartItem['quantity'],
                    'status' => Order::STATUS_PENDING_PAYMENT,
                ]);
            }

            if ($coupon) {
                CouponRedemption::query()->create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'status' => CouponRedemption::STATUS_RESERVED,
                    'discount_cents' => $discountCents,
                ]);
            }

            $this->cart->clear();

            return $order->load('items');
        });
    }

    public function markPaymentSubmitted(Order $order, string $path): void
    {
        $order->update([
            'payment_proof_path' => $path,
            'payment_status' => Order::PAYMENT_SUBMITTED,
            'payment_submitted_at' => now(),
            'payment_auto_checked_at' => now(),
            'payment_auto_check_status' => $this->autoCheckPaymentProof($order, $path),
        ]);

        $this->activity->log('payment_proof_submitted', $order, $order->order_number, [
            'path' => $path,
            'payment_status' => Order::PAYMENT_SUBMITTED,
            'payment_auto_check_status' => $order->payment_auto_check_status,
        ], $order->user);
    }

    public function confirmPayment(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->loadMissing('items.product');

            if (! $order->stock_deducted_at) {
                $this->deductStockForConfirmedOrder($order);
            }

            $nextStatus = $this->confirmedOrderStatus($order);

            $order->update([
                'status' => $nextStatus,
                'payment_status' => Order::PAYMENT_CONFIRMED,
                'paid_at' => now(),
            ]);

            $order->couponRedemption?->update([
                'status' => CouponRedemption::STATUS_CONFIRMED,
            ]);

            $this->activity->log('order_payment_confirmed', $order, $order->order_number, [
                'status' => $nextStatus,
                'payment_status' => Order::PAYMENT_CONFIRMED,
            ], $actor);
        });
    }

    public function markPendingShipment(Order $order, ?User $actor = null): void
    {
        $order->update([
            'status' => Order::STATUS_PENDING_SHIPMENT,
        ]);

        $this->activity->log('order_pending_shipment', $order, $order->order_number, [
            'status' => Order::STATUS_PENDING_SHIPMENT,
        ], $actor);
    }

    public function markIncoming(Order $order, array $data = [], ?User $actor = null): void
    {
        $carrier = isset($data['shipping_carrier_id'])
            ? ShippingCarrier::query()->find($data['shipping_carrier_id'])
            : null;
        $trackingNumber = trim((string) ($data['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($data['tracking_url'] ?? ''));

        if ($trackingUrl === '' && $carrier) {
            $trackingUrl = (string) ($carrier->trackingUrl($trackingNumber) ?? '');
        }

        $order->update([
            'status' => Order::STATUS_INCOMING,
            'shipping_carrier_id' => $carrier?->id,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : $order->tracking_number,
            'tracking_url' => $trackingUrl !== '' ? $trackingUrl : $order->tracking_url,
        ]);

        $order->items()
            ->where('product_status', Product::STATUS_PRESALE)
            ->update([
                'status' => Order::STATUS_INCOMING,
                'incoming_product_id' => $data['incoming_product_id'] ?? null,
            ]);

        $this->activity->log('order_incoming', $order, $order->order_number, [
            'status' => Order::STATUS_INCOMING,
            'incoming_product_id' => $data['incoming_product_id'] ?? null,
        ], $actor);
    }

    public function ship(Order $order, array $data, ?User $actor = null): void
    {
        if (! $order->requires_shipping && $this->hasDigitalDeliveryData($data)) {
            $this->shipDigital($order, $data, $actor);

            return;
        }

        $carrier = isset($data['shipping_carrier_id'])
            ? ShippingCarrier::query()->find($data['shipping_carrier_id'])
            : null;
        $trackingNumber = trim((string) ($data['tracking_number'] ?? ''));
        $trackingUrl = trim((string) ($data['tracking_url'] ?? ''));

        if ($trackingUrl === '' && $carrier) {
            $trackingUrl = (string) ($carrier->trackingUrl($trackingNumber) ?? '');
        }

        $order->update([
            'status' => Order::STATUS_SHIPPED,
            'shipping_carrier_id' => $carrier?->id,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'tracking_url' => $trackingUrl !== '' ? $trackingUrl : null,
            'shipped_at' => now(),
        ]);

        $this->activity->log('order_shipped', $order, $order->order_number, [
            'status' => Order::STATUS_SHIPPED,
            'shipping_carrier_id' => $carrier?->id,
            'tracking_number' => $trackingNumber,
        ], $actor);

        app(WarehouseService::class)->shipOrder($order, $actor, '订单发货自动出库');

        $this->sendShippingMail($order->fresh(['shippingCarrier', 'user']) ?? $order);
    }

    public function shipDigital(Order $order, array $data, ?User $actor = null): void
    {
        $attachmentPaths = $order->digital_delivery_attachment_paths ?: [];

        foreach ($data['digital_delivery_attachments'] ?? [] as $file) {
            if (is_string($file) && $file !== '') {
                $attachmentPaths[] = $file;

                continue;
            }

            if (! $file || ! method_exists($file, 'isValid') || ! $file->isValid()) {
                continue;
            }

            $attachmentPaths[] = $file->store($order->order_number, 'digital_deliveries');
        }

        $order->update([
            'status' => Order::STATUS_AWAITING_RECEIPT,
            'digital_delivery_content' => trim((string) ($data['digital_delivery_content'] ?? $order->digital_delivery_content)),
            'digital_delivery_code' => trim((string) ($data['digital_delivery_code'] ?? $order->digital_delivery_code)),
            'digital_delivery_attachment_paths' => $attachmentPaths,
            'digital_delivery_sent_at' => now(),
            'shipped_at' => now(),
        ]);

        $order->items()->update(['status' => Order::STATUS_AWAITING_RECEIPT]);

        $this->activity->log('order_digital_delivery_sent', $order, $order->order_number, [
            'status' => Order::STATUS_AWAITING_RECEIPT,
            'attachment_count' => count($attachmentPaths),
        ], $actor);
    }

    public function markDigitalDeliveryAccessed(Order $order, User $user): void
    {
        if ($order->user_id !== $user->id || ! $order->hasDigitalDelivery()) {
            return;
        }

        $order->update([
            'status' => Order::STATUS_FULFILLED,
            'digital_delivery_viewed_at' => $order->digital_delivery_viewed_at ?? now(),
            'digital_delivery_completed_at' => $order->digital_delivery_completed_at ?? now(),
            'delivered_at' => $order->delivered_at ?? now(),
            'fulfilled_at' => $order->fulfilled_at ?? now(),
        ]);

        $this->activity->log('order_digital_delivery_completed', $order, $order->order_number, [
            'status' => Order::STATUS_FULFILLED,
        ], $user);
    }

    public function returnToWarehouse(Order $order, ?User $actor = null, ?string $note = null): void
    {
        app(WarehouseService::class)->returnOrder($order, $actor, $note);

        $order->update([
            'admin_note' => $note ?: $order->admin_note,
        ]);

        $this->activity->log('order_returned_to_warehouse', $order, $order->order_number, [
            'status' => $order->status,
            'note' => $note,
        ], $actor);
    }

    public function markAwaitingReceipt(Order $order, ?User $actor = null): void
    {
        $order->update([
            'status' => Order::STATUS_AWAITING_RECEIPT,
        ]);

        $this->activity->log('order_awaiting_receipt', $order, $order->order_number, [
            'status' => Order::STATUS_AWAITING_RECEIPT,
        ], $actor);
    }

    public function confirmReceipt(Order $order, User $user): void
    {
        if ($order->user_id !== $user->id || $order->status !== Order::STATUS_AWAITING_RECEIPT) {
            return;
        }

        $order->update([
            'status' => Order::STATUS_FULFILLED,
            'delivered_at' => now(),
            'fulfilled_at' => now(),
        ]);

        $this->activity->log('order_receipt_confirmed_by_customer', $order, $order->order_number, [
            'status' => Order::STATUS_FULFILLED,
        ], $user);
    }

    public function rejectPayment(Order $order, ?string $note = null, ?User $actor = null): void
    {
        $order->update([
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_auto_check_status' => Order::AUTO_CHECK_FAILED,
            'admin_note' => $note ?: $order->admin_note,
        ]);

        $this->activity->log('order_payment_rejected', $order, $order->order_number, [
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_auto_check_status' => Order::AUTO_CHECK_FAILED,
            'note' => $note,
        ], $actor);
    }

    public function fulfill(Order $order, ?User $actor = null, ?string $reason = null): void
    {
        $adminNote = trim((string) $reason);

        $order->update([
            'status' => Order::STATUS_FULFILLED,
            'delivered_at' => $order->delivered_at ?? now(),
            'fulfilled_at' => now(),
            'admin_note' => $adminNote !== '' ? $adminNote : $order->admin_note,
        ]);

        $this->activity->log('order_fulfilled', $order, $order->order_number, [
            'status' => Order::STATUS_FULFILLED,
            'reason' => $adminNote !== '' ? $adminNote : null,
        ], $actor);
    }

    public function cancel(Order $order, ?User $actor = null, ?string $note = null): void
    {
        if (! $order->isCancellable()) {
            return;
        }

        DB::transaction(function () use ($order, $actor, $note): void {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                if (! $order->stock_deducted_at) {
                    continue;
                }

                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    continue;
                }

                $variant->increment('stock', $item->quantity);
                $variant->refresh();

                InventoryMovement::query()->create([
                    'product_variant_id' => $variant->id,
                    'order_id' => $order->id,
                    'user_id' => $actor?->id,
                    'delta' => $item->quantity,
                    'stock_after' => $variant->stock,
                    'reason' => 'order_cancelled',
                    'note' => $order->order_number,
                ]);

                $product = $variant->product()->first();
                if ($product?->status === Product::STATUS_SOLD_OUT && $product->activeVariants()->sum('stock') > 0) {
                    $product->update(['status' => Product::STATUS_PUBLISHED]);
                }
            }

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'admin_note' => $note ?: $order->admin_note,
            ]);

            $order->couponRedemption?->update([
                'status' => CouponRedemption::STATUS_RELEASED,
            ]);

            $this->activity->log('order_cancelled', $order, $order->order_number, [
                'status' => Order::STATUS_CANCELLED,
                'note' => $note,
            ], $actor);
        });
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'SW'.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function deductStockForConfirmedOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_status !== Product::STATUS_PUBLISHED) {
                $item->update([
                    'status' => $order->requires_shipping ? Order::STATUS_PENDING_SHIPMENT : Order::STATUS_PAID,
                ]);

                continue;
            }

            if (! $item->product_variant_id) {
                throw ValidationException::withMessages([
                    'cart' => "{$item->product_title} 尚未选择规格，无法确认收款。",
                ]);
            }

            $variant = ProductVariant::query()
                ->whereKey($item->product_variant_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || ! $variant->is_active || $variant->stock < $item->quantity) {
                throw ValidationException::withMessages([
                    'cart' => "SKU {$item->variant_sku} 库存不足，无法确认收款。",
                ]);
            }

            $variant->decrement('stock', $item->quantity);
            $variant->refresh();

            $item->update([
                'status' => $order->requires_shipping ? Order::STATUS_PENDING_SHIPMENT : Order::STATUS_PAID,
            ]);

            InventoryMovement::query()->create([
                'product_variant_id' => $variant->id,
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'delta' => -$item->quantity,
                'stock_after' => $variant->stock,
                'reason' => 'payment_confirmed',
                'note' => $order->order_number,
            ]);

            $product = $variant->product()->first();
            if ($product?->status === Product::STATUS_PUBLISHED && $product->activeVariants()->sum('stock') <= 0) {
                $product->update(['status' => Product::STATUS_SOLD_OUT]);
            }
        }

        $order->forceFill(['stock_deducted_at' => now()])->save();
    }

    private function confirmedOrderStatus(Order $order): string
    {
        if ($order->requires_shipping || $order->items->contains('product_status', Product::STATUS_PRESALE)) {
            return Order::STATUS_PENDING_SHIPMENT;
        }

        return Order::STATUS_PAID;
    }

    private function sendShippingMail(Order $order): void
    {
        if (! $order->contact_email) {
            return;
        }

        $originalMailConfig = [
            'mailers.smtp' => config('mail.mailers.smtp'),
            'from' => config('mail.from'),
        ];

        try {
            $settings = SiteSetting::query()->first() ?? new SiteSetting(['site_name' => config('app.name', 'ShopWeb')]);
            $privacy = app(\App\Support\OrderPrivacy::class);
            if ($settings->mail_host) {
                config([
                    'mail.mailers.smtp.host' => $settings->mail_host,
                    'mail.mailers.smtp.port' => $settings->mail_port ?: 587,
                    'mail.mailers.smtp.encryption' => $settings->mail_encryption ?: null,
                    'mail.mailers.smtp.username' => $settings->mail_username,
                    'mail.mailers.smtp.password' => $settings->mail_password,
                    'mail.from.address' => $settings->mail_from_address ?: config('mail.from.address'),
                    'mail.from.name' => $settings->mail_from_name ?: $settings->site_name ?: config('mail.from.name'),
                ]);
            }

            Mail::to($order->contact_email)->send(new OrderShippedMail(
                $order,
                $settings,
                $privacy->canViewOrderNumber($order->user, $settings),
                $privacy->canViewTrackingNumberForOrder($order, $order->user, $settings),
            ));
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            config([
                'mail.mailers.smtp' => $originalMailConfig['mailers.smtp'],
                'mail.from' => $originalMailConfig['from'],
            ]);
        }
    }

    private function autoCheckPaymentProof(Order $order, string $path): string
    {
        $settings = SiteSetting::query()->first();

        if (! ($settings?->payment_auto_check_enabled ?? true)) {
            return Order::AUTO_CHECK_PENDING;
        }

        return $path !== '' && $order->order_number !== '' ? Order::AUTO_CHECK_PASSED : Order::AUTO_CHECK_FAILED;
    }

    private function hasDigitalDeliveryData(array $data): bool
    {
        return filled($data['digital_delivery_content'] ?? null)
            || filled($data['digital_delivery_code'] ?? null)
            || ! empty($data['digital_delivery_attachments'] ?? []);
    }
}
