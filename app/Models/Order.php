<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_PENDING_SHIPMENT = 'pending_shipment';
    public const STATUS_INCOMING = 'incoming';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_AWAITING_RECEIPT = 'awaiting_receipt';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_SUBMITTED = 'submitted';
    public const PAYMENT_CONFIRMED = 'confirmed';
    public const PAYMENT_REJECTED = 'rejected';
    public const AUTO_CHECK_PENDING = 'pending';
    public const AUTO_CHECK_PASSED = 'passed';
    public const AUTO_CHECK_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'subtotal_cents',
        'discount_cents',
        'shipping_fee_cents',
        'shipment_plan',
        'shipment_notice',
        'total_cents',
        'coupon_id',
        'coupon_code',
        'contact_name',
        'contact_phone',
        'contact_email',
        'requires_shipping',
        'shipping_address',
        'shipping_province',
        'shipping_carrier_id',
        'tracking_number',
        'tracking_url',
        'digital_delivery_content',
        'digital_delivery_code',
        'digital_delivery_attachment_paths',
        'digital_delivery_sent_at',
        'digital_delivery_viewed_at',
        'digital_delivery_completed_at',
        'customer_note',
        'payment_proof_path',
        'payment_submitted_at',
        'payment_auto_checked_at',
        'payment_auto_check_status',
        'paid_at',
        'stock_deducted_at',
        'shipped_at',
        'delivered_at',
        'fulfilled_at',
        'cancelled_at',
        'user_deleted_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'requires_shipping' => 'boolean',
            'shipment_plan' => 'array',
            'digital_delivery_attachment_paths' => 'array',
            'digital_delivery_sent_at' => 'datetime',
            'digital_delivery_viewed_at' => 'datetime',
            'digital_delivery_completed_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_auto_checked_at' => 'datetime',
            'paid_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'user_deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingCarrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class);
    }

    public function couponRedemption(): HasOne
    {
        return $this->hasOne(CouponRedemption::class);
    }

    public function couponRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function paymentVerificationLogs(): HasMany
    {
        return $this->hasMany(PaymentVerificationLog::class);
    }

    public function afterSalesRequests(): HasMany
    {
        return $this->hasMany(AfterSalesRequest::class);
    }

    public function isCancellable(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_FULFILLED], true);
    }

    public function userPaymentLabel(): string
    {
        if ($this->payment_status === self::PAYMENT_CONFIRMED) {
            return '已付款';
        }

        if ($this->payment_status === self::PAYMENT_SUBMITTED && $this->payment_auto_check_status === self::AUTO_CHECK_PASSED) {
            return '已付款';
        }

        return match ($this->payment_status) {
            self::PAYMENT_SUBMITTED => '已提交凭证',
            self::PAYMENT_REJECTED => '待支付',
            default => '待支付',
        };
    }

    public function hasDigitalDelivery(): bool
    {
        return filled($this->digital_delivery_content)
            || filled($this->digital_delivery_code)
            || ! empty($this->digital_delivery_attachment_paths);
    }
}
