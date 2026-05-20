<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f6f9fc;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .email-wrapper {
            width: 100%;
            padding: 30px 0;
            background-color: #f6f9fc;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            position: relative;
        }
        .email-content-wrapper {
            position: relative;
            overflow: hidden;
        }
        .email-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            opacity: 0.08;
            pointer-events: none;
            z-index: 1;
            text-align: center;
            width: 100%;
        }
        .email-watermark-text {
            font-size: 42px;
            font-weight: bold;
            color: #666666;
            white-space: nowrap;
        }
        .email-watermark-logo img {
            max-width: 220px;
            max-height: 220px;
        }
        .email-body-content {
            position: relative;
            z-index: 2;
        }
        .header {
            background-color: #FFD700;
            color: #000000;
            text-align: center;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
        }
        .content {
            padding: 25px;
            line-height: 1.6;
        }
        .content h2 {
            margin-top: 0;
            color: #FFD700;
        }
        .invoice-details {
            margin: 20px 0;
            padding: 15px;
            background: #f1f5f9;
            border-radius: 6px;
        }
        .invoice-details p {
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 20px;
            background-color: #FFD700;
            color: #000000!important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding: 15px;
            font-size: 12px;
            color: #999999;
        }
        .footer img {
            margin-top: 10px;
            max-width: 120px;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <div class="email-content-wrapper">
                {{-- Email watermark overlay --}}
                @if(!empty($watermark['enabled']))
                    <div class="email-watermark">
                        @if(!empty($watermark['type']) && $watermark['type'] === 'logo' && !empty($watermark['logo_url']))
                            <div class="email-watermark-logo">
                                <img src="{{ $watermark['logo_url'] }}" alt="Business Logo">
                            </div>
                        @elseif(!empty($watermark['business_name']))
                            <div class="email-watermark-text">{{ $watermark['business_name'] }}</div>
                        @endif
                    </div>
                @endif

                <div class="email-body-content">
                    @if (isset($content))
                        {!! $content !!}
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
