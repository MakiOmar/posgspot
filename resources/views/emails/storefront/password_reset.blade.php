<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Password reset</title>
</head>
<body>
    <!-- Password reset email body -->
    <h1>Reset your password</h1>
    <p>Hello {{ $contact->name }},</p>
    <p>Click the link below to choose a new password. This link expires after use.</p>
    <p><a href="{{ $resetUrl }}">Reset your password</a></p>
    <p>If you did not request a reset, ignore this email.</p>
</body>
</html>
