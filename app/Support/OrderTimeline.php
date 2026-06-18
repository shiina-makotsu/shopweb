<?php

namespace App\Support;

use App\Models\AdminActivityLog;
use App\Models\AfterSalesRequest;
use App\Models\Order;
use App\Models\WarehouseMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OrderTimeline
{
    /**
     * @return array<int, array{time:CarbonInterface,label:string,detail:string,actor:?string,source:string}>
     */
    public function events(Order $order): array
    {
        $order->loadMissing(['items', 'shippingCarrier']);

        $events = collect();

        $this->orderMilestones($order)->each(fn (array $event) => $events->push($event));
        $this->adminActivities($order)->each(fn (array $event) => $events->push($event));
        $this->warehouseMovements($order)->each(fn (array $event) => $events->push($event));
        $this->afterSales($order)->each(fn (array $event) => $events->push($event));

        return $events
            ->filter(fn (array $event): bool => $event['time'] instanceof CarbonInterface)
            ->sortBy(fn (array $event): int => $event['time']->getTimestamp())
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{time:?CarbonInterface,label:string,detail:string,actor:?string,source:string}>
     */
    private function orderMilestones(Order $order): Collection
    {
        $status = app(OrderStatusPresenter::class);

        return collect([
            $this->event($order->created_at, '下单', '订单创建，当前状态：'.$status->label($order->status), null, 'order'),
            $this->event($order->payment_submitted_at, '提交付款凭证', $order->payment_proof_path ? '凭证：'.$order->payment_proof_path : '用户已提交付款凭证', null, 'payment'),
            $this->event($order->paid_at, '确认付款', '付款状态：'.$order->userPaymentLabel(), null, 'payment'),
            $this->event($order->stock_deducted_at, '库存扣减', '已按订单商品扣减库存', null, 'inventory'),
            $this->event($order->digital_delivery_sent_at, '线上交付', '后台已发送线上交付内容', null, 'delivery'),
            $this->event($order->digital_delivery_viewed_at, '查看交付内容', '用户已打开、复制或下载线上交付内容', null, 'delivery'),
            $this->event($order->shipped_at, '发货', trim(implode(' / ', array_filter([
                $order->shippingCarrier?->name,
                $order->tracking_number,
            ]))) ?: '订单已发货', null, 'shipping'),
            $this->event($order->delivered_at, '收货', '用户或后台确认收货', null, 'shipping'),
            $this->event($order->fulfilled_at, '完成', $order->admin_note ?: '订单已完成', null, 'order'),
            $this->event($order->cancelled_at, '取消', $order->admin_note ?: '订单已取消', null, 'order'),
            $this->event($order->user_deleted_at, '用户隐藏订单', '用户已从前台订单列表隐藏该订单', null, 'order'),
        ])->filter(fn (array $event): bool => $event['time'] !== null)->values();
    }

    /**
     * @return Collection<int, array{time:CarbonInterface,label:string,detail:string,actor:?string,source:string}>
     */
    private function adminActivities(Order $order): Collection
    {
        return AdminActivityLog::query()
            ->with('user')
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->oldest()
            ->get()
            ->map(fn (AdminActivityLog $log): array => $this->event(
                $log->created_at,
                $this->activityLabel($log->action),
                $log->description ?: $log->action,
                $log->user?->displayName() ?? $log->user?->name,
                'activity',
            ));
    }

    /**
     * @return Collection<int, array{time:CarbonInterface,label:string,detail:string,actor:?string,source:string}>
     */
    private function warehouseMovements(Order $order): Collection
    {
        return WarehouseMovement::query()
            ->with(['warehouse', 'user', 'variant'])
            ->where('order_id', $order->id)
            ->oldest()
            ->get()
            ->map(function (WarehouseMovement $movement): array {
                $label = WarehouseMovement::typeOptions()[$movement->type] ?? $movement->type;
                $detail = trim(implode(' / ', array_filter([
                    $movement->warehouse?->name,
                    $movement->variant?->sku,
                    '数量变动 '.$movement->delta,
                    $movement->note,
                ])));

                return $this->event($movement->created_at, $label, $detail, $movement->user?->displayName(), 'warehouse');
            });
    }

    /**
     * @return Collection<int, array{time:CarbonInterface,label:string,detail:string,actor:?string,source:string}>
     */
    private function afterSales(Order $order): Collection
    {
        return AfterSalesRequest::query()
            ->where('order_id', $order->id)
            ->oldest()
            ->get()
            ->flatMap(function (AfterSalesRequest $request): array {
                $events = [
                    $this->event($request->created_at, '售后需求', $request->subject ?: $request->message, null, 'after_sales'),
                ];

                if ($request->resolved_at) {
                    $events[] = $this->event($request->resolved_at, '售后处理完成', $request->admin_note ?: $request->resolution_type ?: '已处理', null, 'after_sales');
                }

                return $events;
            });
    }

    /**
     * @return array{time:?CarbonInterface,label:string,detail:string,actor:?string,source:string}
     */
    private function event(?CarbonInterface $time, string $label, ?string $detail = null, ?string $actor = null, string $source = 'order'): array
    {
        return [
            'time' => $time,
            'label' => $label,
            'detail' => trim((string) $detail),
            'actor' => $actor,
            'source' => $source,
        ];
    }

    private function activityLabel(string $action): string
    {
        return [
            'payment_proof_submitted' => '提交付款凭证',
            'order_payment_confirmed' => '后台确认收款',
            'order_pending_shipment' => '标记待发货',
            'order_incoming' => '标记进货中',
            'order_shipped' => '后台发货',
            'order_digital_delivery_sent' => '线上交付',
            'order_digital_delivery_completed' => '线上交付完成',
            'order_returned_to_warehouse' => '退回入库',
            'order_awaiting_receipt' => '标记待收货',
            'order_receipt_confirmed_by_customer' => '用户确认收货',
            'order_payment_rejected' => '驳回付款凭证',
            'order_fulfilled' => '后台标记完成',
            'order_cancelled' => '取消订单',
            'order_manually_updated' => '后台修改订单',
            'order_quick_shipping_updated' => '后台补充物流',
        ][$action] ?? $action;
    }
}
