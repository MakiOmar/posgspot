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
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .email-content-cell {
            padding: 0;
        }
        .header {
            background-color: #f37c16;
            color: #ffffff;
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
            color: #f37c16;
        }
        .invoice-details {
            margin: 20px 0;
            padding: 15px;
            background: rgba(241, 245, 249, 0.85);
            border-radius: 6px;
        }
        .invoice-details p {
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 20px;
            background-color: #f37c16;
            color: #ffffff!important;
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
        .wm-fallback-row td {
            padding: 18px 4px;
            text-align: center;
            color: #d0d0d0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 16px;
            letter-spacing: 2px;
            line-height: 1;
        }
    </style>
</head>
<body>
    @php
        $watermark_style = $watermark_style ?? '';
        $watermark_background_url = $watermark_background_url ?? '';
        $has_tile_background = ! empty($watermark_background_url);
    @endphp
    <div class="email-wrapper">
        <table role="presentation" class="email-container" width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="max-width: 600px; margin: 0 auto; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);">
            <tr>
                <td
                    class="email-content-cell"
                    @if($has_tile_background) background="{{ $watermark_background_url }}" @endif
                    bgcolor="#ffffff"
                    style="padding: 0; background-color: #ffffff; {{ $watermark_style }}"
                >
                    @if(!empty($watermark['enabled']) && !$has_tile_background)
                        @include('emails.partials.watermark_html_fallback')
                    @endif

                    @if (isset($content))
                        {!! $content !!}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
