<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('lang_v1.storefront_test_email_heading') }}</title>
</head>
<body>
    <!-- Storefront settings: test message for one notification inbox -->
    <h1>{{ __('lang_v1.storefront_test_email_heading') }}</h1>
    <p>{{ __('lang_v1.storefront_test_email_body', ['type' => $typeLabel]) }}</p>
    <p>{{ __('lang_v1.storefront_test_email_recipient') }}: <strong>{{ $recipient }}</strong></p>
    <p>{{ __('lang_v1.storefront_test_email_sent_at') }}: {{ $sentAt }}</p>
    <p>{{ $businessName }}</p>
</body>
</html>
