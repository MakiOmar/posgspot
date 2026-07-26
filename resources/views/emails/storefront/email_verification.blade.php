<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verify your email</title>
</head>
<body>
    <!-- Storefront email verification OTP -->
    <h1>Verify your email</h1>
    <p>Hello {{ $contact->name }},</p>
    <p>Your verification code is:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>
    <p>This code expires in 30 minutes. If you did not create an account, ignore this email.</p>
</body>
</html>
