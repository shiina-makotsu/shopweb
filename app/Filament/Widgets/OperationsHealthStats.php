<?php

namespace App\Filament\Widgets;

use App\Models\AiUsageLog;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationsHealthStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = '运营监控';

    protected function getStats(): array
    {
        $orderStats = $this->orderStats();
        $supportStats = $this->supportStats();
        $analyticsStats = $this->analyticsStats();
        $aiStats = $this->aiStats();
        $systemStats = $this->systemStats();

        return [
            Stat::make('待确认收款', (string) $orderStats['pending_payment_proofs'])
                ->description('用户已提交付款信息')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color($orderStats['pending_payment_proofs'] > 0 ? 'warning' : 'success'),
            Stat::make('待发货/交付', (string) $orderStats['pending_fulfillment'])
                ->description('已付款但仍需处理')
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color($orderStats['pending_fulfillment'] > 0 ? 'warning' : 'success'),
            Stat::make('今日成交额', Money::format($orderStats['paid_amount_today']))
                ->description($orderStats['paid_orders_today'].' 个已确认订单')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('客服未读', (string) $supportStats['unread_customer_messages'])
                ->description($supportStats['open_sessions'].' 个进行中会话')
                ->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color($supportStats['unread_customer_messages'] > 0 ? 'danger' : 'success'),
            Stat::make('今日访问', (string) $analyticsStats['page_views'])
                ->description($analyticsStats['unique_sessions'].' 个会话')
                ->descriptionIcon(Heroicon::OutlinedEye)
                ->color('gray'),
            Stat::make('商品转化', $analyticsStats['conversion_rate'].'%')
                ->description('加购率 '.$analyticsStats['add_to_cart_rate'].'%')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color($analyticsStats['conversion_rate'] > 0 ? 'success' : 'warning'),
            Stat::make('AI 24h 用量', $this->compactNumber($aiStats['tokens_24h']))
                ->description($aiStats['failed_24h'].' 次失败调用')
                ->descriptionIcon(Heroicon::OutlinedCpuChip)
                ->color($aiStats['failed_24h'] > 0 ? 'warning' : 'gray'),
            Stat::make('失败队列', (string) $systemStats['failed_jobs'])
                ->description('磁盘剩余 '.$systemStats['disk_free_gb'].' GB')
                ->descriptionIcon(Heroicon::OutlinedServerStack)
                ->color($systemStats['failed_jobs'] > 0 || $systemStats['disk_used_percent'] > 90 ? 'danger' : 'success'),
        ];
    }

    /**
     * @return array{pending_payment_proofs:int,pending_fulfillment:int,paid_orders_today:int,paid_amount_today:int}
     */
    private function orderStats(): array
    {
        if (! Schema::hasTable('orders')) {
            return [
                'pending_payment_proofs' => 0,
                'pending_fulfillment' => 0,
                'paid_orders_today' => 0,
                'paid_amount_today' => 0,
            ];
        }

        return [
            'pending_payment_proofs' => Order::query()
                ->where('payment_status', Order::PAYMENT_SUBMITTED)
                ->count(),
            'pending_fulfillment' => Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->whereIn('status', [
                    Order::STATUS_PAID,
                    Order::STATUS_PENDING_SHIPMENT,
                    Order::STATUS_INCOMING,
                ])
                ->count(),
            'paid_orders_today' => Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)
                ->where(function ($query): void {
                    $query
                        ->whereDate('fulfilled_at', today())
                        ->orWhere(function ($query): void {
                            $query
                                ->whereNull('fulfilled_at')
                                ->whereDate('paid_at', today());
                        });
                })
                ->count(),
            'paid_amount_today' => (int) Order::query()
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->where('status', Order::STATUS_FULFILLED)
                ->where(function ($query): void {
                    $query
                        ->whereDate('fulfilled_at', today())
                        ->orWhere(function ($query): void {
                            $query
                                ->whereNull('fulfilled_at')
                                ->whereDate('paid_at', today());
                        });
                })
                ->sum('total_cents'),
        ];
    }

    /**
     * @return array{unread_customer_messages:int,open_sessions:int}
     */
    private function supportStats(): array
    {
        if (! Schema::hasTable('support_chat_messages') || ! Schema::hasTable('support_chat_sessions')) {
            return ['unread_customer_messages' => 0, 'open_sessions' => 0];
        }

        return [
            'unread_customer_messages' => SupportChatMessage::query()
                ->whereIn('sender_type', [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST])
                ->whereNull('read_at')
                ->whereHas('session', fn ($query) => $query->whereNotIn('status', [
                    SupportChatSession::STATUS_ENDED,
                    SupportChatSession::STATUS_CLOSED,
                ]))
                ->count(),
            'open_sessions' => SupportChatSession::query()
                ->whereNotIn('status', [
                    SupportChatSession::STATUS_ENDED,
                    SupportChatSession::STATUS_CLOSED,
                ])
                ->count(),
        ];
    }

    /**
     * @return array{page_views:int,unique_sessions:int,conversion_rate:float,add_to_cart_rate:float}
     */
    private function analyticsStats(): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return [
                'page_views' => 0,
                'unique_sessions' => 0,
                'conversion_rate' => 0,
                'add_to_cart_rate' => 0,
            ];
        }

        $today = AnalyticsEvent::query()->whereDate('created_at', today());
        $productViews = (clone $today)->where('event', AnalyticsEvent::PRODUCT_VIEW)->count();
        $addToCart = (clone $today)->where('event', AnalyticsEvent::ADD_TO_CART)->count();
        $orders = (clone $today)->where('event', AnalyticsEvent::ORDER_CREATED)->count();

        return [
            'page_views' => (clone $today)->where('event', AnalyticsEvent::PAGE_VIEW)->count(),
            'unique_sessions' => (clone $today)->where('event', AnalyticsEvent::PAGE_VIEW)->distinct('session_id')->count('session_id'),
            'conversion_rate' => $productViews > 0 ? round(($orders / $productViews) * 100, 2) : 0,
            'add_to_cart_rate' => $productViews > 0 ? round(($addToCart / $productViews) * 100, 2) : 0,
        ];
    }

    /**
     * @return array{tokens_24h:int,failed_24h:int}
     */
    private function aiStats(): array
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return ['tokens_24h' => 0, 'failed_24h' => 0];
        }

        $recent = AiUsageLog::query()->where('created_at', '>=', now()->subDay());

        return [
            'tokens_24h' => (int) (clone $recent)->sum('token_count'),
            'failed_24h' => (clone $recent)->where('status', '!=', 'success')->count(),
        ];
    }

    /**
     * @return array{failed_jobs:int,disk_free_gb:float,disk_used_percent:float}
     */
    private function systemStats(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        $failedJobs = 0;

        try {
            $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        } catch (Throwable) {
            $failedJobs = 0;
        }

        return [
            'failed_jobs' => (int) $failedJobs,
            'disk_free_gb' => $free ? round($free / 1073741824, 2) : 0,
            'disk_used_percent' => ($free && $total) ? round((1 - ($free / $total)) * 100, 2) : 0,
        ];
    }

    private function compactNumber(int $value): string
    {
        if ($value >= 1000000) {
            return round($value / 1000000, 2).'m';
        }

        if ($value >= 1000) {
            return round($value / 1000, 2).'k';
        }

        return (string) $value;
    }
}
