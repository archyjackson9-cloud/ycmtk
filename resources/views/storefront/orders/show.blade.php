@extends('layouts.storefront')

@section('title', 'Order Details #' . $order->order_number)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">

    {{-- Order Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 pb-6 border-b border-gray-200/70">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">Order #{{ $order->order_number }}</h1>
                <span class="inline-flex items-center text-xs font-bold px-3 py-1 rounded-full {{ $order->status->badgeClasses() }} shadow-2xs">
                    {{ $order->status->label() }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Placed on {{ $order->created_at->format('d M Y, \a\t H:i') }} • Tracked via SMS</p>
        </div>
        <div>
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-700 hover:text-brand-900 transition-colors">
                <span>← Back to Storefront</span>
            </a>
        </div>
    </div>

    {{-- Tracking Timeline Glass Card --}}
    <div class="liquid-glass rounded-[28px] p-6 sm:p-8 mb-8 shadow-xs">
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-400 mb-4">Harvest & Delivery Status</h2>
        @include('partials.order-status-timeline', ['order' => $order])
    </div>

    {{-- Action Needed for On Hold --}}
    @if($order->status === \App\Enums\OrderStatus::OnHold)
        <div class="bg-amber-50/70 backdrop-blur-xl border border-amber-200/70 rounded-3xl p-6 mb-8 shadow-xs">
            <div class="flex items-center gap-2 text-amber-900 font-extrabold text-sm mb-1">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Customer Action Required</span>
            </div>
            <p class="text-xs text-amber-800 mb-4 leading-relaxed">{{ $order->on_hold_reason }} Please select how you would like to proceed:</p>
            <form action="{{ route('orders.resolve-hold', $order) }}" method="POST" class="flex flex-wrap gap-3">
                @csrf
                <button type="submit" name="preference" value="refund" class="border border-amber-400 bg-white text-amber-900 hover:bg-amber-100 font-bold rounded-full px-5 py-2.5 text-xs shadow-2xs transition-all">Full Refund via MoMo</button>
                <button type="submit" name="preference" value="credit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-full px-5 py-2.5 text-xs shadow-xs transition-all">Credit for Next-Day Harvest</button>
            </form>
        </div>
    @endif

    {{-- Pending Payment Notice --}}
    @if($order->status === \App\Enums\OrderStatus::PendingPayment)
        <div class="liquid-glass rounded-[28px] p-6 mb-8 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-gray-900">Awaiting Hubtel Payment Verification</h3>
                    <p class="text-[11px] text-gray-500">Stock is temporarily reserved for this order.</p>
                </div>
            </div>
            <form action="{{ route('orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Cancel this order and release stock?')">
                @csrf
                <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-800 transition-colors">Cancel Order</button>
            </form>
        </div>
    @endif

    {{-- Grid of Order Info --}}
    <div class="grid lg:grid-cols-12 gap-8 items-start">
        
        {{-- Items and Breakdown --}}
        <div class="lg:col-span-8 liquid-glass-strong rounded-[28px] p-6 sm:p-8 shadow-xs space-y-6">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900 pb-3 border-b border-gray-100">
                Harvest Items Ordered
            </h2>

            <div class="space-y-4 divide-y divide-gray-100">
                @foreach($order->items as $item)
                    <div class="flex items-center justify-between text-xs pt-4 first:pt-0">
                        <div>
                            <p class="font-bold text-gray-900 text-sm">{{ $item->product_name }}</p>
                            <p class="text-gray-400 mt-0.5">{{ $item->quantity }} × {{ config('cymarket.currency_symbol') }}{{ number_format($item->unit_price, 2) }}</p>
                        </div>
                        <p class="font-black text-gray-900 text-sm">{{ config('cymarket.currency_symbol') }}{{ number_format($item->line_total, 2) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-100 pt-5 space-y-2 text-xs">
                <div class="flex justify-between text-gray-600">
                    <span>Items Subtotal</span>
                    <span class="font-bold text-gray-900">{{ config('cymarket.currency_symbol') }}{{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Delivery Fee</span>
                    <span class="font-bold text-gray-900">{{ config('cymarket.currency_symbol') }}{{ number_format($order->delivery_fee, 2) }}</span>
                </div>
                @if($order->discount_total > 0)
                    <div class="flex justify-between text-brand-700 font-bold">
                        <span>Discount Applied ({{ $order->coupon_code }})</span>
                        <span>-{{ config('cymarket.currency_symbol') }}{{ number_format($order->discount_total, 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-black text-gray-900 text-base pt-3 border-t border-gray-200">
                    <span>Total Paid</span>
                    <span class="text-brand-800">{{ config('cymarket.currency_symbol') }}{{ number_format($order->total, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Delivery & Payment Column --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Delivery Info Card --}}
            <div class="liquid-glass rounded-[28px] p-6 shadow-xs space-y-3">
                <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Delivery Destination
                </h2>
                <div class="text-xs space-y-1 text-gray-600">
                    <p class="font-bold text-gray-900">{{ $order->delivery_recipient_name }}</p>
                    <p>{{ $order->delivery_phone }}</p>
                    <p class="pt-1 text-gray-700">{{ $order->delivery_address_line }}</p>
                    @if($order->delivery_landmark)
                        <p class="text-gray-500 font-medium">Near: {{ $order->delivery_landmark }}</p>
                    @endif
                    @if($order->deliveryZone)
                        <div class="mt-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-brand-50 text-brand-800 text-[11px] font-bold">
                            <span>Zone: {{ $order->deliveryZone->name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Payment Info Card --}}
            @if($order->payments->isNotEmpty())
                <div class="liquid-glass rounded-[28px] p-6 shadow-xs space-y-3">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Hubtel MoMo Payment
                    </h2>
                    @foreach($order->payments as $payment)
                        <div class="flex items-center justify-between text-xs py-1.5 border-b border-gray-100 last:border-0">
                            <span class="text-gray-400 font-medium">{{ $payment->created_at->format('d M, H:i') }}</span>
                            <span class="{{ $payment->status->badgeClasses() }} px-2.5 py-0.5 rounded-full text-[11px] font-bold">
                                {{ $payment->status->label() }}
                            </span>
                        </div>
                    @endforeach

                    @if($order->status === \App\Enums\OrderStatus::PendingPayment && $order->latestPayment()?->status === \App\Enums\PaymentStatus::Pending)
                        @php($pendingPayment = $order->latestPayment())
                        @if(config('hubtel.mode', 'sandbox') !== 'live')
                            <div class="pt-2">
                                <a href="{{ route('checkout.sandbox-pay', $pendingPayment->reference) }}" 
                                   class="block text-center bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-full py-2.5 shadow-xs transition-all">
                                    Simulate Sandbox Payment →
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

    </div>

</div>
@endsection
