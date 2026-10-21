<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $copy['headline'] }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6;">
<tr><td align="center" style="padding:16px 8px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
        <tr><td style="background-color:#047857;padding:20px 24px;color:#ffffff;font-size:20px;font-weight:700;">{{ config('app.name') }}</td></tr>

        <tr><td style="padding:24px 24px 8px 24px;">
            <h1 style="margin:0 0 8px 0;font-size:22px;line-height:1.3;color:#065f46;">{{ $copy['headline'] }}</h1>
            <p style="margin:0 0 4px 0;font-size:14px;color:#6b7280;">Order {{ $order->order_number }}</p>
            <p style="margin:12px 0 0 0;font-size:15px;line-height:1.6;">Hello {{ $order->customerName() }},</p>
            <p style="margin:8px 0 0 0;font-size:15px;line-height:1.6;">{{ $copy['intro'] }}</p>
        </td></tr>

        <tr><td style="padding:16px 24px 0 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#ecfdf5;border-radius:8px;">
                <tr><td style="padding:14px 16px;font-size:15px;line-height:1.6;">
                    <strong style="color:#065f46;">What happens next</strong><br>{{ $copy['next'] }}
                </td></tr>
            </table>
        </td></tr>

        @if ($showPayment)
        <tr><td style="padding:16px 24px 0 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #047857;border-radius:8px;">
                <tr><td style="padding:14px 16px;font-size:14px;line-height:1.7;">
                    <strong style="color:#065f46;font-size:15px;">How to pay (pay by transfer)</strong><br>
                    {{ $payment['instructions'] }}<br>
                    <strong>Network / bank:</strong> {{ $payment['network'] }}<br>
                    <strong>Account name:</strong> {{ $payment['account_name'] }}<br>
                    <strong>Account number:</strong> {{ $payment['account_number'] }}<br>
                    <strong>Amount to send:</strong> {{ $currency }}{{ number_format((float) $order->total, 2) }}<br>
                    <strong>Reference:</strong> {{ $order->order_number }}
                </td></tr>
            </table>
        </td></tr>
        @endif

        <tr><td style="padding:20px 24px 0 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-collapse:collapse;">
                <tr>
                    <th align="left" style="padding:8px 0;border-bottom:2px solid #047857;color:#065f46;">Item</th>
                    <th align="center" style="padding:8px 6px;border-bottom:2px solid #047857;color:#065f46;">Qty</th>
                    <th align="right" style="padding:8px 0;border-bottom:2px solid #047857;color:#065f46;">Total</th>
                </tr>
                @foreach ($items as $item)
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid #e5e7eb;">{{ $item->product_name }}<br><span style="color:#6b7280;font-size:12px;">{{ $currency }}{{ number_format((float) $item->unit_price, 2) }} each</span></td>
                    <td align="center" style="padding:8px 6px;border-bottom:1px solid #e5e7eb;">{{ $item->quantity }}</td>
                    <td align="right" style="padding:8px 0;border-bottom:1px solid #e5e7eb;">{{ $currency }}{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
                @endforeach
                <tr><td colspan="2" style="padding:8px 0 2px 0;color:#6b7280;">Subtotal</td><td align="right" style="padding:8px 0 2px 0;">{{ $currency }}{{ number_format((float) $order->subtotal, 2) }}</td></tr>
                @if ((float) $order->discount_total > 0)
                <tr><td colspan="2" style="padding:2px 0;color:#6b7280;">Discount</td><td align="right" style="padding:2px 0;">-{{ $currency }}{{ number_format((float) $order->discount_total, 2) }}</td></tr>
                @endif
                <tr><td colspan="2" style="padding:2px 0;color:#6b7280;">Delivery</td><td align="right" style="padding:2px 0;">{{ $currency }}{{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
                <tr><td colspan="2" style="padding:8px 0;font-weight:700;border-top:1px solid #e5e7eb;">Total</td><td align="right" style="padding:8px 0;font-weight:700;border-top:1px solid #e5e7eb;color:#065f46;">{{ $currency }}{{ number_format((float) $order->total, 2) }}</td></tr>
            </table>
        </td></tr>

        <tr><td style="padding:16px 24px 0 24px;font-size:14px;line-height:1.7;">
            <strong style="color:#065f46;">Delivery</strong><br>
            {{ $order->delivery_recipient_name }}, {{ $order->delivery_phone }}<br>
            {{ $order->delivery_address_line }}@if ($order->delivery_landmark) ({{ $order->delivery_landmark }})@endif<br>
            @if ($zoneName)Zone: {{ $zoneName }}<br>@endif
            @if ($scheduledLabel)Scheduled delivery: <strong>{{ $scheduledLabel }}</strong><br>@endif
            Status: <strong>{{ $order->status->label() }}</strong>
        </td></tr>

        <tr><td align="center" style="padding:24px;">
            <a href="{{ $trackingUrl }}" style="display:inline-block;background-color:#047857;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:14px 28px;border-radius:8px;">Track your order</a>
            <p style="margin:12px 0 0 0;font-size:12px;color:#6b7280;word-break:break-all;">Or open: <a href="{{ $trackingUrl }}" style="color:#047857;">{{ $trackingUrl }}</a></p>
        </td></tr>

        <tr><td style="background-color:#f9fafb;padding:16px 24px;font-size:12px;color:#6b7280;text-align:center;">
            Thanks for shopping with {{ config('app.name') }}. This email is about order {{ $order->order_number }}.
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
