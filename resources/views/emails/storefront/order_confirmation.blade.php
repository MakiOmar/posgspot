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
</body>
</html>
