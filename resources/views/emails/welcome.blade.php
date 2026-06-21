<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        /* Email-safe inline-friendly styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f8f9fa;
            padding: 20px 0;
            margin: 0;
        }
        .container {
            max-width: 560px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid #eaeaea;
        }
        .header {
            background-color: #0d6efd;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        .content {
            padding: 32px 24px;
            font-size: 15px;
        }
        .content p {
            margin: 0 0 16px;
        }
        .button {
            display: inline-block;
            background-color: #0d6efd;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            margin: 8px 0 16px;
        }
        .footer {
            padding: 18px 24px;
            font-size: 13px;
            color: #6c757d;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ config('app.name') }}!</h1>
        </div>
        <div class="content">
            <p>Hello {{ $user->name ?? $user->full_name ?? 'there' }},</p>

            <p>Thank you for verifying your account. We're excited to have you on board!</p>

            <p>You can now access all the features of your account.</p>

            <p style="text-align: center; margin-top: 24px;">
                <a href="{{ url('/') }}" class="button">Go to Dashboard</a>
            </p>

            <p>If you have any questions, feel free to reach out to our support team.</p>
        </div>
        <div class="footer">
            Best regards,<br>
            {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
