<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AfterSalesRequest;
use App\Models\NavigationMenuItem;
use App\Models\PrivateMessage;
use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Notifications\OrderPaymentTimeoutNotification;
use App\Services\CartService;
use App\Services\StorefrontCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StorefrontViewData
{
    private ?SiteSetting $settings = null;

    private ?array $data = null;

    public function __construct(
        private readonly StorefrontCache $cache,
        private readonly CartService $cart,
        private readonly UserOrderSummary $orders,
    ) {}

    public function settings(): ?SiteSetting
    {
        return $this->settings ??= $this->cache->settings();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $cartItems = $this->cart->items();
        $orderSummary = $this->orders->forUser(Auth::user());

        return $this->data = [
            'siteSettings' => $this->settings(),
            'storeCategories' => $this->cache->categories(),
            'storePages' => $this->cache->pages(),
            'storeMenuItems' => $this->cache->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV),
            'storeTopNavItems' => $this->cache->menuItems(NavigationMenuItem::PLACEMENT_TOP_NAV),
            'storeHomeInfoMenuItems' => $this->cache->menuItems(NavigationMenuItem::PLACEMENT_HOME_INFO),
            'cartItemCount' => $cartItems->sum('quantity'),
            'cartSubtotalCents' => $cartItems->sum('line_total_cents'),
            'unreadAnnouncementCount' => $this->unreadAnnouncementCount(),
            'privateUnreadMessageCount' => $this->privateUnreadMessageCount(),
            ...$this->orderTimeoutNotifications(),
            ...$this->supportUnreadCounts(),
            'pendingPaymentOrderCount' => $orderSummary['pending_payment'],
            'awaitingReceiptOrderCount' => $orderSummary['awaiting_receipt'],
            'userOrderNoticeCount' => $orderSummary['notice'],
            'popupAnnouncement' => $this->popupAnnouncement(),
        ];
    }

    private function unreadAnnouncementCount(): int
    {
        try {
            if (! Auth::check() || ! Schema::hasTable('announcements')) {
                return 0;
            }

            return Announcement::query()
                ->published()
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', Auth::id()))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function popupAnnouncement(): ?Announcement
    {
        try {
            if (! Auth::check() || ! Schema::hasTable('announcements')) {
                return null;
            }

            return Announcement::query()
                ->published()
                ->where('popup_when_unread', true)
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', Auth::id()))
                ->orderByDesc('is_pinned')
                ->latest('published_at')
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function privateUnreadMessageCount(): int
    {
        try {
            if (! Auth::check() || ! Schema::hasTable('private_messages')) {
                return 0;
            }

            return PrivateMessage::query()
                ->where('recipient_id', Auth::id())
                ->whereNull('read_at')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array{orderTimeoutNotificationCount:int,orderTimeoutNotification:mixed} */
    private function orderTimeoutNotifications(): array
    {
        try {
            if (! Auth::check() || ! Schema::hasTable('notifications')) {
                return ['orderTimeoutNotificationCount' => 0, 'orderTimeoutNotification' => null];
            }

            $query = Auth::user()
                ->unreadNotifications()
                ->where('type', OrderPaymentTimeoutNotification::class);

            return [
                'orderTimeoutNotificationCount' => (clone $query)->count(),
                'orderTimeoutNotification' => $query->latest()->first(),
            ];
        } catch (Throwable) {
            return ['orderTimeoutNotificationCount' => 0, 'orderTimeoutNotification' => null];
        }
    }

    /** @return array{supportChatUnreadMessageCount:int, supportTicketUnreadCount:int, afterSalesUnreadCount:int, supportCaseUnreadCount:int, supportUnreadMessageCount:int} */
    private function supportUnreadCounts(): array
    {
        try {
            if (! Auth::check()) {
                return $this->emptySupportUnreadCounts();
            }

            $chat = Schema::hasTable('support_chat_messages') && Schema::hasTable('support_chat_sessions')
                ? SupportChatMessage::query()
                    ->whereIn('sender_type', [SupportChatMessage::SENDER_ADMIN, SupportChatMessage::SENDER_SYSTEM])
                    ->whereNull('support_quick_reply_id')
                    ->whereNull('read_at')
                    ->whereHas('session', fn ($query) => $query
                        ->where('user_id', Auth::id())
                        ->whereIn('status', [SupportChatSession::STATUS_OPEN, SupportChatSession::STATUS_ACTIVE]))
                    ->count()
                : 0;
            $tickets = Schema::hasTable('support_tickets') && Schema::hasColumn('support_tickets', 'customer_read_at')
                ? SupportTicket::query()
                    ->where('user_id', Auth::id())
                    ->whereNotNull('admin_reply')
                    ->whereNull('customer_read_at')
                    ->count()
                : 0;
            $afterSales = Schema::hasTable('after_sales_requests') && Schema::hasColumn('after_sales_requests', 'customer_read_at')
                ? AfterSalesRequest::query()
                    ->where('user_id', Auth::id())
                    ->whereNotNull('admin_note')
                    ->whereNull('customer_read_at')
                    ->count()
                : 0;

            return [
                'supportChatUnreadMessageCount' => $chat,
                'supportTicketUnreadCount' => $tickets,
                'afterSalesUnreadCount' => $afterSales,
                'supportCaseUnreadCount' => $tickets + $afterSales,
                'supportUnreadMessageCount' => $chat + $tickets + $afterSales,
            ];
        } catch (Throwable) {
            return $this->emptySupportUnreadCounts();
        }
    }

    private function emptySupportUnreadCounts(): array
    {
        return [
            'supportChatUnreadMessageCount' => 0,
            'supportTicketUnreadCount' => 0,
            'afterSalesUnreadCount' => 0,
            'supportCaseUnreadCount' => 0,
            'supportUnreadMessageCount' => 0,
        ];
    }
}
