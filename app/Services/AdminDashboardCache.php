<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\AnalyticsEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Support\ProfitMetrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminDashboardCache
{
    /**
     * @return array{today_sales:int,month_sales:int,pending_payments:int,low_stock_variants:int,profit:array<string,mixed>}
     */
    public function dashboardStats(): array
    {
        return Cache::remember('shop:admin:dashboard-stats', now()->addSeconds(30), function (): array {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();

            return [
                'today_sales' => $this->completedSalesBetween($todayStart, $todayEnd),
                'month_sales' => $this->completedSalesBetween($monthStart, $monthEnd),
                'pending_payments' => $this->pendingPaymentCount(),
                'low_stock_variants' => $this->lowStockVariantCount(),
                'profit' => app(ProfitMetrics::class)->summary(),
            ];
        });
    }

    /**
     * @return array{pending_payment_proofs:int,pending_fulfillment:int,paid_orders_today:int,paid_amount_today:int}
     */
    public function orderStats(): array
    {
        return Cache::remember('shop:admin:operation-order-stats', now()->addSeconds(20), function (): array {
            if (! $this->tableReady('orders')) {
                return [
                    'pending_payment_proofs' => 0,
                    'pending_fulfillment' => 0,
                    'paid_orders_today' => 0,
                    'paid_amount_today' => 0,
                ];
            }

            return [
                'pending_payment_proofs' => Order::query()
                    ->awaitingPaymentReview()
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
        });
    }

    /**
     * @return array{unread_customer_messages:int,open_sessions:int}
     */
    public function supportStats(): array
    {
        return Cache::remember('shop:admin:operation-support-stats', now()->addSeconds(15), function (): array {
            if (! $this->tableReady('support_chat_messages') || ! $this->tableReady('support_chat_sessions')) {
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
        });
    }

    /**
     * @return array{page_views:int,unique_sessions:int,conversion_rate:float,add_to_cart_rate:float}
     */
    public function analyticsStats(): array
    {
        return Cache::remember('shop:admin:operation-analytics-stats', now()->addSeconds(30), function (): array {
            if (! $this->tableReady('analytics_events')) {
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
        });
    }

    /**
     * @return array{tokens_24h:int,failed_24h:int}
     */
    public function aiStats(): array
    {
        return Cache::remember('shop:admin:operation-ai-stats', now()->addSeconds(30), function (): array {
            if (! $this->tableReady('ai_usage_logs')) {
                return ['tokens_24h' => 0, 'failed_24h' => 0];
            }

            $recent = AiUsageLog::query()->where('created_at', '>=', now()->subDay());

            return [
                'tokens_24h' => (int) (clone $recent)->sum('token_count'),
                'failed_24h' => (clone $recent)->where('status', '!=', 'success')->count(),
            ];
        });
    }

    /**
     * @return array{failed_jobs:int,disk_free_gb:float,disk_used_percent:float}
     */
    public function systemStats(): array
    {
        return Cache::remember('shop:admin:operation-system-stats', now()->addSeconds(15), function (): array {
            $path = storage_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $failedJobs = 0;

            try {
                $failedJobs = $this->tableReady('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            } catch (Throwable) {
                $failedJobs = 0;
            }

            return [
                'failed_jobs' => (int) $failedJobs,
                'disk_free_gb' => $free ? round($free / 1073741824, 2) : 0,
                'disk_used_percent' => ($free && $total) ? round((1 - ($free / $total)) * 100, 2) : 0,
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    public function prewarm(): array
    {
        $this->dashboardStats();
        $this->orderStats();
        $this->supportStats();
        $this->analyticsStats();
        $this->aiStats();
        $this->systemStats();

        return [
            'shop:admin:dashboard-stats',
            'shop:admin:operation-order-stats',
            'shop:admin:operation-support-stats',
            'shop:admin:operation-analytics-stats',
            'shop:admin:operation-ai-stats',
            'shop:admin:operation-system-stats',
        ];
    }

    private function completedSalesBetween(mixed $start, mixed $end): int
    {
        if (! $this->tableReady('orders')) {
            return 0;
        }

        return (int) Order::query()
            ->where('payment_status', Order::PAYMENT_CONFIRMED)
            ->where('status', Order::STATUS_FULFILLED)
            ->where(function ($query) use ($start, $end): void {
                $query
                    ->whereBetween('fulfilled_at', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end): void {
                        $query
                            ->whereNull('fulfilled_at')
                            ->whereBetween('paid_at', [$start, $end]);
                    });
            })
            ->sum('total_cents');
    }

    private function pendingPaymentCount(): int
    {
        if (! $this->tableReady('orders')) {
            return 0;
        }

        return Order::query()
            ->awaitingPaymentReview()
            ->count();
    }

    private function lowStockVariantCount(): int
    {
        if (! $this->tableReady('product_variants') || ! $this->tableReady('products')) {
            return 0;
        }

        return ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->whereNotIn('fulfillment_type', [
                Product::FULFILLMENT_ONLINE,
                Product::FULFILLMENT_CONTACT_LEGACY,
            ]))
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->count();
    }

    private function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
