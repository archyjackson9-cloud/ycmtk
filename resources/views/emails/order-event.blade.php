<x-mail::message>
# {{ $event->label() }}

{{ $bodyText }}

<x-mail::table>
| | |
|---|---|
| **Order** | {{ $order->order_number }} |
| **Total** | {{ config('cymarket.currency_symbol') }}{{ number_format($order->total, 2) }} |
| **Status** | {{ $order->status->label() }} |
</x-mail::table>

<x-mail::button :url="$trackingUrl">
Track your order
</x-mail::button>

Thanks for shopping with CY-Market.

{{ config('app.name') }}
</x-mail::message>
