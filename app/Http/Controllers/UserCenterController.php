<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'profile_intro' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public_uploads')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public_uploads');
        }

        unset($data['avatar']);

        $user->update($data);

        return back()->with('status', '个人资料已更新。');
    }
}
