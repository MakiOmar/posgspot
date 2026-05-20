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
        }
        .email-content-cell {
            padding: 0;
        }
        .header {
            background-color: rgba(255, 215, 0, 0.88);
            color: #000000;
            text-align: center;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
        }
        .content {
            padding: 25px;
            line-height: 1.6;
            background-color: transparent;
        }
        .content h2 {
            margin-top: 0;
            color: #FFD700;
        }
        .invoice-details {
            margin: 20px 0;
            padding: 15px;
            background: rgba(241, 245, 249, 0.82);
            border-radius: 6px;
        }
        .invoice-details p {
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 20px;
            background-color: rgba(255, 215, 0, 0.92);
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
            background-color: transparent;
        }
        .footer img {
            margin-top: 10px;
            max-width: 120px;
        }
    </style>
</head>
<body>
    @php
        $watermark_style = $watermark_style ?? '';
    @endphp
    <div class="email-wrapper">
        <table role="presentation" class="email-container" width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
            <tr>
                <td class="email-content-cell" style="padding: 0; {{ $watermark_style }}">
                    @if (isset($content))
                        {!! $content !!}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
