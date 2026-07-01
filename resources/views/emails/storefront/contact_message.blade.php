<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contact form message</title>
</head>
<body>
    <p><strong>Name:</strong> {{ $submission['name'] }}</p>
    <p><strong>Email:</strong> {{ $submission['email'] }}</p>
    <p><strong>Phone:</strong> {{ $submission['phone'] }}</p>
    <p><strong>Message:</strong></p>
    <p>{!! nl2br(e($submission['message'])) !!}</p>
</body>
</html>
