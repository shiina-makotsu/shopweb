<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\OrderPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public SiteSetting $settings,
        private readonly bool $canViewOrderNumber,
        private readonly bool $canViewTrackingNumber,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->settings->shipping_mail_subject ?: '你的订单已发货',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.shipped',
            with: [
                'displayOrderNumber' => $this->canViewOrderNumber
                    ? $this->order->order_number
                    : app(OrderPrivacy::class)->displayOrderNumber($this->order, $this->order->user, $this->settings),
                'displayTrackingNumber' => $this->canViewTrackingNumber
                    ? ($this->order->tracking_number ?: '-')
                    : '后台已隐藏',
            ],
        );
    }
}
