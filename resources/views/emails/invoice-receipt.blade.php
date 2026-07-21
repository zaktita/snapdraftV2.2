<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your receipt</title>
</head>
<body style="font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; line-height: 1.5; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p>Hi {{ $userName }},</p>
    <p>Here is your Lemon Squeezy receipt for invoice <strong>#{{ $invoiceNumber }}</strong> ({{ $total }}).</p>
    <p>
        <a href="{{ $receiptUrl }}" style="display: inline-block; padding: 10px 16px; background: #c45c26; color: #fff; text-decoration: none; border-radius: 8px;">
            View receipt on Lemon Squeezy
        </a>
    </p>
    <p style="color: #666; font-size: 14px;">If the button doesn’t work, open this link:<br>{{ $receiptUrl }}</p>
    <p>— SnapDraft</p>
</body>
</html>
