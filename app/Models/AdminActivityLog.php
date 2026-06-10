<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminActivityLog extends Model
{
    use HasFactory;

    private const ACTION_LABELS = [
        'payment_proof_submitted' => '用户提交付款凭证',
        'order_payment_confirmed' => '后台确认收款',
        'order_pending_shipment' => '订单标记为待发货',
        'order_incoming' => '订单标记为进货中',
        'order_shipped' => '订单发货',
        'order_digital_delivery_sent' => '发送线上交付内容',
        'order_digital_delivery_completed' => '用户查看线上交付内容',
        'order_returned_to_warehouse' => '退回仓库',
        'order_awaiting_receipt' => '订单标记为待签收',
        'order_receipt_confirmed_by_customer' => '用户确认签收',
        'order_payment_rejected' => '驳回付款凭证',
        'order_fulfilled' => '订单标记为已完成',
        'order_cancelled' => '取消订单',
        'customers_csv_imported' => '导入前台用户 CSV',
        'products_csv_imported' => '导入商品/SKU CSV',
    ];

    private const PROPERTY_LABELS = [
        'status' => '订单状态',
        'payment_status' => '付款状态',
        'result' => '结果',
        'processed' => '处理数量',
        'success' => '成功数量',
        'failed' => '失败数量',
        'total' => '总数',
        'count' => '数量',
        'order_number' => '订单号',
        'path' => '凭证文件',
        'payment_auto_check_status' => '自动校验',
        'payment_auto_check_message' => '自动校验说明',
        'shipping_carrier_id' => '物流承运商 ID',
        'tracking_number' => '物流单号',
        'tracking_url' => '物流查询链接',
        'incoming_product_id' => '进货商品 ID',
        'incoming_note' => '进货说明',
        'attachment_count' => '附件数量',
        'note' => '备注',
        'reason' => '原因',
        'admin_note' => '后台备注',
        'restock' => '恢复库存',
        'imported' => '导入数量',
        'created' => '新增数量',
        'updated' => '更新数量',
        'skipped' => '跳过数量',
        'rows' => '行数',
        'message' => '消息',
    ];

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? str($this->action)
            ->replace(['_', '.'], ' ')
            ->headline()
            ->toString();
    }

    public function subjectLabel(): string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return '-';
        }

        return class_basename($this->subject_type).' #'.$this->subject_id;
    }

    public function propertiesSummary(): string
    {
        $properties = $this->properties ?: [];

        if ($properties === []) {
            return '无附加信息';
        }

        return collect($properties)
            ->map(fn ($value, string|int $key): string => (self::PROPERTY_LABELS[(string) $key] ?? (string) $key).'：'.$this->formatPropertyValue($value))
            ->implode('；');
    }

    private function formatPropertyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '-';
            }

            if (array_is_list($value)) {
                return collect($value)->map(fn ($item): string => $this->formatPropertyValue($item))->implode('、');
            }

            return collect($value)
                ->map(function (mixed $item, string|int $key): string {
                    $label = self::PROPERTY_LABELS[(string) $key] ?? str((string) $key)->headline()->toString();

                    return $label.'：'.$this->formatPropertyValue($item);
                })
                ->implode('、');
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
