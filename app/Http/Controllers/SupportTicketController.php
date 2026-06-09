<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $guestId = $this->guestId($request);
        $selectedOrder = $this->selectedOrder($request);

        return view('support.index', [
            'tickets' => SupportTicket::query()
                ->with('order')
                ->when($request->user(), fn ($query) => $query->whereBelongsTo($request->user()))
                ->when(! $request->user(), fn ($query) => $query->where('guest_id', $guestId))
                ->latest()
                ->paginate(10),
            'guestId' => $request->user() ? null : $guestId,
            'selectedOrder' => $selectedOrder,
            'orders' => $request->user()
                ? $request->user()->orders()->latest()->limit(50)->get(['id', 'order_number', 'status', 'total_cents', 'created_at'])
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:consultation,complaint,after_sale,other'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $order = $this->selectedOrder($request, $data['order_id'] ?? null);

        SupportTicket::query()->create([
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'guest_id' => $request->user() ? null : $this->guestId($request),
            'guest_email' => $request->user() ? null : ($data['guest_email'] ?? null),
            'category' => $data['category'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return back()->with('status', '客服会话已提交，后台处理后会在这里显示回复。');
    }

    private function selectedOrder(Request $request, mixed $orderId = null): ?Order
    {
        if (! $request->user()) {
            return null;
        }

        $id = $orderId ?? $request->query('order_id');

        if (! $id) {
            return null;
        }

        return Order::query()
            ->whereBelongsTo($request->user())
            ->whereKey($id)
            ->first();
    }

    private function guestId(Request $request): string
    {
        if (! $request->session()->has('support_guest_id')) {
            $request->session()->put('support_guest_id', 'guest_'.strtolower(bin2hex(random_bytes(5))));
        }

        return (string) $request->session()->get('support_guest_id');
    }
}
