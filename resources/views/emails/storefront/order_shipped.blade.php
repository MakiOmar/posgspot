<p>Hi,</p>
<p>Your order <strong>{{ $order['invoice_no'] ?? $order['storefront_order_id'] ?? '' }}</strong> has shipped.</p>
@if(!empty($order['shipping_carrier']) || !empty($order['shipping_tracking_number']))
<p>
  @if(!empty($order['shipping_carrier'])) Carrier: {{ $order['shipping_carrier'] }}<br>@endif
  @if(!empty($order['shipping_tracking_number'])) Tracking: {{ $order['shipping_tracking_number'] }}<br>@endif
  @if(!empty($order['shipping_tracking_url']))
    <a href="{{ $order['shipping_tracking_url'] }}">Track shipment</a>
  @endif
</p>
@endif
<p>Thank you for shopping with us.</p>
