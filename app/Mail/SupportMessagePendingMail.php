<?php

namespace App\Mail;

use App\Models\SiteSetting;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportMessagePendingMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SupportChatSession $session,
        public SupportChatMessage $message,
        public SiteSetting $settings,
    ) {}

    public function envelope(): Envelope
    {
        $siteName = $this->settings->site_name ?: config('app.name', 'ShopWeb');

        return new Envelope(
            subject: '['.$siteName.'] 有新的客服待处理消息',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.pending-message',
        );
    }
}
