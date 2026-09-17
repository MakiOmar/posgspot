<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Product request</title>
</head>
<body>
    <p><strong>Product:</strong> {{ $productRequest->product_name }}</p>
    @if($productRequest->platform)
        <p><strong>Platform:</strong> {{ $productRequest->platform }}</p>
    @endif
    <p><strong>Status:</strong> {{ $productRequest->status }}</p>
    <p><strong>Name:</strong> {{ $productRequest->name }}</p>
    <p><strong>Email:</strong> {{ $productRequest->email }}</p>
    <p><strong>Phone:</strong> {{ $productRequest->phone }}</p>
    @if($productRequest->notes)
        <p><strong>Notes:</strong></p>
        <p>{!! nl2br(e($productRequest->notes)) !!}</p>
    @endif
    <p><small>Request #{{ $productRequest->id }} — open Product requests in POS admin to follow up.</small></p>
</body>
</html>
