@php
    $layout = !empty($receipt_details->design)
        ? 'sale_pos.receipts.' . $receipt_details->design
        : 'sale_pos.receipts.classic';
@endphp
<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
<link rel="stylesheet" href="{{ asset('css/brand.css?v='.$asset_v) }}">
@include('sale_pos.receipts.partials.invoice_styles')
@include($layout, [
    'receipt_details' => $receipt_details,
    'location_details' => $location_details ?? null,
    'is_email_attachment' => $is_email_attachment ?? false,
])
