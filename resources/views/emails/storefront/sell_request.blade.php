<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sell to us request</title>
</head>
<body>
    <p><strong>Type:</strong> {{ $sellRequest->type }}</p>
    <p><strong>Status:</strong> {{ $sellRequest->status }}</p>
    <p><strong>Name:</strong> {{ $sellRequest->name }}</p>
    <p><strong>Email:</strong> {{ $sellRequest->email }}</p>
    <p><strong>Phone:</strong> {{ $sellRequest->phone }}</p>
    <p><strong>City:</strong> {{ $sellRequest->city }}</p>
    <p><strong>Purchased from us:</strong> {{ $sellRequest->purchased_from_us ? 'Yes' : 'No' }}</p>
    @if($sellRequest->invoice_no)
        <p><strong>Invoice:</strong> {{ $sellRequest->invoice_no }}</p>
    @endif
    @if($sellRequest->transaction_id)
        <p><strong>Transaction ID:</strong> {{ $sellRequest->transaction_id }}</p>
    @endif
    @if(!empty($sellRequest->details) && is_array($sellRequest->details))
        <p><strong>Details:</strong></p>
        <ul>
            @foreach($sellRequest->details as $key => $value)
                <li><strong>{{ $key }}:</strong> {{ is_scalar($value) ? $value : json_encode($value) }}</li>
            @endforeach
        </ul>
    @endif
    @if($sellRequest->notes)
        <p><strong>Notes:</strong></p>
        <p>{!! nl2br(e($sellRequest->notes)) !!}</p>
    @endif
    <p><small>Request #{{ $sellRequest->id }} — open Sell requests in POS admin to follow up.</small></p>
</body>
</html>
