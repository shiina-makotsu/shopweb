<p>{{ $order->contact_name }}，你好：</p>

<p>你的订单已经发货。</p>

<ul>
    <li>订单：{{ $displayOrderNumber }}</li>
    <li>承运商：{{ $order->shippingCarrier?->name ?? '暂无' }}</li>
    <li>物流单号：{{ $displayTrackingNumber }}</li>
</ul>

@if($order->tracking_url)
    <p><a href="{{ $order->tracking_url }}">查看物流进度</a></p>
@endif

@if($settings->shipping_mail_template)
    <p>{!! nl2br(e($settings->shipping_mail_template)) !!}</p>
@endif
