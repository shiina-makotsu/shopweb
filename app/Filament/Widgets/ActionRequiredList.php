<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\SupportChatSessionResource;
use App\Models\AiUsageLog;
use App\Models\Order;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ActionRequiredList extends Widget
{
    protected static bool $isLazy = true;

    protected string $view = 'filament.widgets.action-required-list';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'items' => $this->items(),
        ];
    }

    private function items(): Collection
    {
        $items = collect();

        if (Schema::hasTable('orders')) {
            Order::query()
                ->with('user')
                ->where('payment_status', Order::PAYMENT_SUBMITTED)
                ->latest('payment_submitted_at')
                ->limit(5)
                ->get()
                ->each(fn (Order $order) => $items->push([
                    'type' => '待确认收款',
                    'title' => $order->order_number,
                    'detail' => ($order->user?->email ?: $order->contact_name ?: '未知用户').' 提交了付款信息',
                    'time' => $order->payment_submitted_at ?? $order->updated_at,
                    'url' => OrderResource::getUrl('edit', ['record' => $order]),
                    'tone' => 'warning',
                ]));

            Order::query()
                ->with('user')
                ->where('payment_status', Order::PAYMENT_CONFIRMED)
                ->whereIn('status', [Order::STATUS_PAID, Order::STATUS_PENDING_SHIPMENT, Order::STATUS_INCOMING])
                ->latest('paid_at')
                ->limit(5)
                ->get()
                ->each(fn (Order $order) => $items->push([
                    'type' => '待发货/交付',
                    'title' => $order->order_number,
                    'detail' => ($order->user?->email ?: $order->contact_name ?: '未知用户').' 等待后台处理',
                    'time' => $order->paid_at ?? $order->updated_at,
                    'url' => OrderResource::getUrl('edit', ['record' => $order]),
                    'tone' => 'info',
                ]));
        }

        if (Schema::hasTable('support_chat_messages') && Schema::hasTable('support_chat_sessions')) {
            SupportChatMessage::query()
                ->with('session.user')
                ->whereIn('sender_type', [SupportChatMessage::SENDER_CUSTOMER, SupportChatMessage::SENDER_GUEST])
                ->whereNull('read_at')
                ->whereHas('session', fn ($query) => $query->whereNotIn('status', [
                    SupportChatSession::STATUS_ENDED,
                    SupportChatSession::STATUS_CLOSED,
                ]))
                ->latest()
                ->limit(5)
                ->get()
                ->each(fn (SupportChatMessage $message) => $items->push([
                    'type' => '客服未读',
                    'title' => '会话 #'.$message->support_chat_session_id,
                    'detail' => str($message->body ?: '附件消息')->limit(90)->toString(),
                    'time' => $message->created_at,
                    'url' => $message->session ? SupportChatSessionResource::getUrl('edit', ['record' => $message->session]) : null,
                    'tone' => 'danger',
                ]));
        }

        if (Schema::hasTable('ai_usage_logs')) {
            AiUsageLog::query()
                ->where('status', '!=', 'success')
                ->latest()
                ->limit(5)
                ->get()
                ->each(fn (AiUsageLog $log) => $items->push([
                    'type' => 'AI 失败',
                    'title' => $log->model ?: $log->feature ?: 'AI 请求',
                    'detail' => ($log->endpoint_host ?: '未知服务商').' / '.$log->status,
                    'time' => $log->created_at,
                    'url' => null,
                    'tone' => 'gray',
                ]));
        }

        return $items
            ->sortByDesc(fn (array $item) => $item['time']?->timestamp ?? 0)
            ->take(12)
            ->values();
    }
}
