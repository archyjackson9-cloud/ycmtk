@extends('layouts.storefront')

@section('title', 'My Harvest Orders — CY-Market')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">

    <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200/70">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">My Harvest Orders</h1>
            <p class="text-xs text-gray-500 mt-1">Review your recent farm-fresh deliveries and tracking history</p>
        </div>
        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-700 hover:text-brand-900 transition-colors">
            <span>+ Order more produce</span>
        </a>
    </div>

    @if($orders->isEmpty())
        <div class="max-w-md mx-auto my-12 liquid-glass rounded-[32px] p-10 text-center shadow-xs space-y-4">
            <div class="h-16 w-16 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <div>
                <h2 class="text-base font-black text-gray-900">No orders placed yet</h2>
                <p class="text-xs text-gray-500 mt-1">Your completed harvest orders will appear here for easy tracking.</p>
            </div>
            <div>
                <a href="{{ route('products.index') }}" 
                   class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-6 py-2.5 rounded-full shadow-xs transition-all">
                    <span>Explore Produce</span>
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <a href="{{ route('orders.show', $order) }}"
                   class="group flex flex-col sm:flex-row sm:items-center justify-between gap-4 liquid-glass rounded-[28px] p-5 shadow-xs hover:shadow-md hover:-translate-y-0.5 liquid-tap transition-all">
                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-brand-50 text-brand-700 group-hover:bg-brand-600 group-hover:text-white flex items-center justify-center font-bold text-sm shrink-0 transition-colors shadow-2xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-black text-gray-900 text-sm tracking-tight group-hover:text-brand-700 transition-colors">#{{ $order->order_number }}</span>
                                <span class="inline-block text-[11px] font-bold px-2.5 py-0.5 rounded-full {{ $order->status->badgeClasses() }}">
                                    {{ $order->status->label() }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                Placed on {{ $order->created_at->format('d M Y, H:i') }} • {{ $order->items->count() }} item(s)
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-6 sm:border-l sm:border-gray-100 sm:pl-6">
                        <div class="text-left sm:text-right">
                            <span class="text-base font-black text-gray-900 tracking-tight block">
                                {{ config('cymarket.currency_symbol') }}{{ number_format($order->total, 2) }}
                            </span>
                            <span class="text-[11px] font-medium text-gray-400">Total</span>
                        </div>
                        <div class="h-8 w-8 rounded-full bg-gray-50 group-hover:bg-brand-50 text-gray-400 group-hover:text-brand-700 flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
