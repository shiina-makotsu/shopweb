<p>后台有新的客服待处理消息。</p>

<ul>
    <li>会话：#{{ $session->id }}</li>
    <li>用户：{{ $session->user?->displayName() ?? $session->guest_id ?? '访客' }}</li>
    @if($session->order)
        <li>订单：{{ $session->order->order_number }}</li>
    @endif
    <li>时间：{{ $message->created_at?->format('Y-m-d H:i') }}</li>
</ul>

@if($message->body)
    <p style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($message->body, 500) }}</p>
@endif

<p>请登录后台客服会话页面处理。</p>
