<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order confirmation</title>
</head>
<body>
    <!-- Order confirmation email body -->
    <h1>Thank you for your order</h1>
    <p>Order number: <strong>{{ $order['invoice_no'] ?? '' }}</strong></p>
    <p>Total: <strong>{{ $order['final_total'] ?? '' }}</strong></p>
    <p>Payment status: {{ $order['payment_status'] ?? '' }}</p>

    @if(!empty($order['digital_deliveries']) && is_array($order['digital_deliveries']))
        <hr>
        <h2>Your digital delivery</h2>
        <p>Keep these details private. Do not share them with anyone.</p>
        @foreach($order['digital_deliveries'] as $delivery)
            <div style="margin-bottom: 1rem; padding: 0.75rem; border: 1px solid #ddd;">
                @if(!empty($delivery['title']))
                    <p><strong>{{ $delivery['title'] }}</strong></p>
                @endif
                @if(($delivery['kind'] ?? '') === 'card')
                    <p>Code: <strong>{{ $delivery['code'] ?? '' }}</strong></p>
                @else
                    <p>Account: <strong>{{ $delivery['account_email'] ?? '' }}</strong></p>
                    <p>Password: <strong>{{ $delivery['account_password'] ?? '' }}</strong></p>
                @endif
            </div>
        @endforeach
    @endif
</body>
</html>
