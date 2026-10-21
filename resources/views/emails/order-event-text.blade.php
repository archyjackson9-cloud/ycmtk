{!! strtoupper(config('app.name')) !!}
{!! $copy['headline'] !!}
Order {!! $order->order_number !!}

Hello {!! $order->customerName() !!},

{!! $copy['intro'] !!}

What happens next:
{!! $copy['next'] !!}
@if ($showPayment)

HOW TO PAY (pay by transfer)
{!! $payment['instructions'] !!}
Network / bank: {!! $payment['network'] !!}
Account name: {!! $payment['account_name'] !!}
Account number: {!! $payment['account_number'] !!}
Amount to send: {!! $currency !!}{!! number_format((float) $order->total, 2) !!}
Reference: {!! $order->order_number !!}
@endif

ITEMS
@foreach ($items as $item)
- {!! $item->product_name !!} x {!! $item->quantity !!}  {!! $currency !!}{!! number_format((float) $item->line_total, 2) !!}
@endforeach
Subtotal: {!! $currency !!}{!! number_format((float) $order->subtotal, 2) !!}
@if ((float) $order->discount_total > 0)
Discount: -{!! $currency !!}{!! number_format((float) $order->discount_total, 2) !!}
@endif
Delivery: {!! $currency !!}{!! number_format((float) $order->delivery_fee, 2) !!}
Total: {!! $currency !!}{!! number_format((float) $order->total, 2) !!}

DELIVERY
{!! $order->delivery_recipient_name !!}, {!! $order->delivery_phone !!}
{!! $order->delivery_address_line !!}
@if ($zoneName)
Zone: {!! $zoneName !!}
@endif
@if ($scheduledLabel)
Scheduled delivery: {!! $scheduledLabel !!}
@endif
Status: {!! $order->status->label() !!}

Track your order: {!! $trackingUrl !!}

Thanks for shopping with {!! config('app.name') !!}.
