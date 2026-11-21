<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('lang_v1.your_login_code') }}</title>
</head>
<body>
    <h1>{{ __('lang_v1.your_login_code') }}</h1>
    <p>Hello {{ $user->user_full_name }},</p>
    <p>{{ __('lang_v1.staff_login_otp_body', ['app' => config('app.name', 'POS')]) }}</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>
    <p>{{ __('lang_v1.staff_login_otp_expires', ['minutes' => $expireMinutes]) }}</p>
</body>
</html>
