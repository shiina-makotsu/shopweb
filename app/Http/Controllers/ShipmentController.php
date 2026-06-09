<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\OrderPrivacy;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function show(Request $request, OrderPrivacy $privacy): View
    {
        $order = null;

        if ($request->filled('order_number')) {
            $order = Order::query()
                ->with(['shippingCarrier', 'user'])
                ->whereBelongsTo($request->user())
                ->where('order_number', $request->string('order_number')->toString())
                ->first();
        }

        return view('shipments.show', [
            'order' => $order,
            'searched' => $request->filled('order_number'),
            'settings' => SiteSetting::query()->first(),
            'privacy' => $privacy,
        ]);
    }
}
