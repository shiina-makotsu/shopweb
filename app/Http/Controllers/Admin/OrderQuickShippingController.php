<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderQuickShippingController extends Controller
{
    public function update(Request $request, Order $order, AdminActivityLogger $activity): RedirectResponse
    {
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

        if ($changes !== []) {
            $order->update($updates);

            $activity->log('order_quick_shipping_updated', $order->fresh(), trim((string) ($data['admin_note'] ?? '')) ?: '后台列表补充物流信息', [
                'order_number' => $order->order_number,
                'note' => trim((string) ($data['admin_note'] ?? '')) ?: '后台列表补充物流信息',
                'changes' => $changes,
            ], $request->user());
        }

        return back();
    }
}
