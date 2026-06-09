<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserCenterController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $orderCounts = Order::query()
            ->whereBelongsTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('user.center', [
            'user' => $user->loadCount(['wishlists', 'favorites']),
            'recentHistories' => $user->browsingHistories()
                ->with('product.coverMedia')
                ->latest('viewed_at')
                ->limit(8)
                ->get(),
            'orderCounts' => $orderCounts,
            'pendingPaymentCount' => (int) ($orderCounts[Order::STATUS_PENDING_PAYMENT] ?? 0),
            'pendingShipmentCount' => (int) ($orderCounts[Order::STATUS_PENDING_SHIPMENT] ?? 0) + (int) ($orderCounts[Order::STATUS_INCOMING] ?? 0),
            'awaitingReceiptCount' => (int) ($orderCounts[Order::STATUS_SHIPPED] ?? 0) + (int) ($orderCounts[Order::STATUS_AWAITING_RECEIPT] ?? 0),
            'fulfilledCount' => (int) ($orderCounts[Order::STATUS_FULFILLED] ?? 0),
        ]);
    }

    public function section(Request $request, string $section): View|RedirectResponse
    {
        $user = $request->user();

        $allowed = ['profile', 'wishlists', 'favorites', 'addresses', 'privacy', 'interface', 'membership'];
        abort_unless(in_array($section, $allowed, true), 404);

        if ($section === 'profile') {
            return redirect()->route('users.show', $user);
        }

        return view('user.section', [
            'user' => $user,
            'section' => $section,
            'wishlists' => $section === 'wishlists'
                ? $user->wishlists()->with(['product.coverMedia', 'product.variants'])->latest()->paginate(12)
                : null,
            'favorites' => $section === 'favorites'
                ? $user->favorites()->with(['product.coverMedia', 'product.variants'])->latest()->paginate(12)
                : null,
        ]);
    }
}
