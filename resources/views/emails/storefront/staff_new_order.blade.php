<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('lang_v1.storefront_new_order_title') }}</title>
</head>
<body>
    <!-- Staff alert: new website order -->
    <h1>{{ __('lang_v1.storefront_new_order_title') }}</h1>
    <p>{{ __('sale.invoice_no') }}: <strong>{{ $summary['invoice_no'] }}</strong></p>
    <p>{{ __('lang_v1.storefront_new_order_customer') }}: {{ $summary['customer_name'] }} {{ $summary['customer_mobile'] }}</p>
    @if($summary['location'] !== '')
        <p>{{ __('business.business_location') }}: {{ $summary['location'] }}</p>
    @endif

    <!-- Ordered items -->
    <ul>
        @foreach($summary['items'] as $item)
            <li>{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }} × {{ $item['name'] }}</li>
        @endforeach
    </ul>

    <p>{{ __('sale.total') }}: <strong>{{ $summary['total'] }} {{ $summary['currency'] }}</strong></p>
    <p>
        {{ __('lang_v1.payment_method') }}:
        {{ $summary['payment'] === 'cod' ? __('lang_v1.storefront_payment_cod') : __('lang_v1.storefront_payment_online') }}
        ({{ $summary['payment_status'] }})
    </p>
    @if($summary['shipping_method'] !== '' || $summary['shipping_address'] !== '')
        <p>{{ __('lang_v1.storefront_new_order_delivery') }}: {{ $summary['shipping_method'] }} {{ $summary['shipping_address'] }}</p>
    @endif
    @if($summary['note'] !== '')
        <p>{{ __('lang_v1.storefront_new_order_note') }}: {{ $summary['note'] }}</p>
    @endif

    <!-- Digital-only orders need no packing -->
    @if($summary['digital_only'])
        <p><em>{{ __('lang_v1.storefront_new_order_digital_only') }}</em></p>
    @endif

    <p><a href="{{ $orderUrl }}">{{ __('lang_v1.storefront_new_order_open') }}</a></p>
</body>
</html>
