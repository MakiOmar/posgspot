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
    <p>Use this token to reset your password:</p>
    <p><strong>{{ $token }}</strong></p>
    <p>If you did not request a reset, ignore this email.</p>
</body>
</html>
