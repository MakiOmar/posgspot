<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password reset</title>
</head>
<body>
    <h1>Reset your password</h1>
    <p>Hello {{ $contact->name }},</p>
    <p>Use this code in the Games Spot app (or on the website) to choose a new password. It expires after use.</p>
    <p style="font-size: 28px; letter-spacing: 6px; font-weight: 700;">{{ $code }}</p>
    <p>Or open the app: <a href="{{ $appUrl }}">Reset in the Games Spot app</a></p>
    <p>Website fallback: <a href="{{ $resetUrl }}">Reset your password</a></p>
    <p>If you did not request a reset, ignore this email.</p>
</body>
</html>
