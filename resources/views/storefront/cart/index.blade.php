@extends('layouts.storefront')

@section('title', 'Your Harvest Cart — CY-Market')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">

    {{-- Breadcrumb & Title --}}
    <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-200/70">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">Your Harvest Cart</h1>
            <p class="text-xs text-gray-500 mt-1">Direct from our fields — packed cold and fresh</p>
        </div>
        @if($cart->items->isNotEmpty())
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-50 border border-brand-200/60 text-xs font-bold text-brand-800">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
                {{ $cart->items->count() }} item(s) selected
            </span>
        @endif
    </div>

    @if($cart->items->isEmpty())
        <div class="max-w-md mx-auto my-12 liquid-glass rounded-[32px] p-10 text-center shadow-xs space-y-5">
            <div class="h-20 w-20 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-black text-gray-900">Your cart is currently empty</h2>
                <p class="text-xs text-gray-500 mt-1">Looks like you haven't added any farm-fresh produce to your basket yet.</p>
            </div>
            <div>
                <a href="{{ route('products.index') }}" 
                   class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-6 py-3 rounded-full shadow-md shadow-brand-600/20 hover:scale-105 transition-all">
                    <span>Explore Farm Produce</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>
        </div>
    @else
        <div class="grid lg:grid-cols-12 gap-8 items-start">
            
            {{-- Cart Items List --}}
            <div class="lg:col-span-8 space-y-4">
                @foreach($cart->items as $item)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 liquid-glass rounded-[28px] p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow">
                        {{-- Image --}}
                        <div class="h-20 w-20 rounded-2xl bg-gray-50 border border-gray-100 overflow-hidden shrink-0">
                            @if($img = $item->product->images->first())
                                <img src="{{ $img->url() }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                            @else
                                <div class="h-full w-full flex items-center justify-center text-gray-300">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $item->product->category->name ?? 'Harvest' }}</span>
                            <a href="{{ route('products.show', $item->product) }}" class="font-bold text-sm text-gray-900 hover:text-brand-700 transition-colors truncate block">
                                {{ $item->product->name }}
                            </a>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ config('cymarket.currency_symbol') }}{{ number_format($item->unit_price, 2) }} <span class="text-[11px]">/ {{ $item->product->unit_of_measurement }}</span>
                            </p>
                        </div>

                        {{-- Quantity Stepper --}}
                        <div class="flex items-center gap-3">
                            <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center liquid-input rounded-full p-0.5">
                                @csrf @method('PATCH')
                                <button type="button" 
                                        onclick="const inp = this.nextElementSibling; if(inp.value > {{ $item->product->min_order_quantity }}) { inp.value--; this.form.submit(); }"
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-gray-500 hover:bg-white hover:text-gray-900 font-bold transition-colors">-</button>
                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="{{ $item->product->min_order_quantity }}"
                                       class="w-10 text-center text-xs font-bold text-gray-900 border-0 p-0 bg-transparent focus:ring-0"
                                       onchange="this.form.submit()">
                                <button type="button" 
                                        onclick="const inp = this.previousElementSibling; inp.value++; this.form.submit();"
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-gray-500 hover:bg-white hover:text-gray-900 font-bold transition-colors">+</button>
                            </form>

                            {{-- Line Total --}}
                            <p class="w-24 text-right font-black text-sm text-gray-900">
                                {{ config('cymarket.currency_symbol') }}{{ number_format($item->lineTotal(), 2) }}
                            </p>

                            {{-- Remove --}}
                            <form action="{{ route('cart.destroy', $item) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit" 
                                        aria-label="Remove" 
                                        class="h-8 w-8 rounded-full flex items-center justify-center text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach

                <div class="pt-2">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-700 hover:text-brand-900 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Continue browsing fresh harvest</span>
                    </a>
                </div>
            </div>

            {{-- Sticky Order Summary --}}
            <div class="lg:col-span-4 sticky top-28 space-y-4">
                <div class="glass-card rounded-3xl p-6 shadow-md space-y-5">
                    <h2 class="text-base font-black text-gray-900 tracking-tight pb-3 border-b border-gray-100">
                        Harvest Order Summary
                    </h2>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span class="font-bold text-gray-900 text-sm">{{ config('cymarket.currency_symbol') }}{{ number_format($cart->subtotal(), 2) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-500">
                            <span>Delivery fee</span>
                            <span class="text-emerald-700 font-semibold">Calculated at checkout</span>
                        </div>
                    </div>

                    <div class="p-3 rounded-2xl bg-brand-50/70 border border-brand-200/50 text-[11px] text-brand-900 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
                            {{ config('cymarket.pilot_zone_name') }} Delivery
                        </div>
                        <p class="text-brand-800/80">Dispatched directly from harvest beds in temperature-safe packaging.</p>
                    </div>

                    <div class="pt-2">
                        <a href="{{ route('checkout.index') }}" 
                           class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-sm py-3.5 px-6 rounded-full shadow-lg shadow-brand-600/25 hover:shadow-xl hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                            <span>Proceed to Checkout</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>

                    {{-- Trust Badges --}}
                    <div class="pt-3 border-t border-gray-100 text-center space-y-2">
                        <p class="text-[10px] uppercase tracking-wider font-extrabold text-gray-400">Guaranteed Safe Checkout</p>
                        <div class="flex items-center justify-center gap-2 text-xs font-bold text-gray-600">
                            <span class="px-2 py-0.5 rounded bg-gray-100 border border-gray-200 text-[11px]">Hubtel</span>
                            <span class="px-2 py-0.5 rounded bg-gray-100 border border-gray-200 text-[11px]">MTN MoMo</span>
                            <span class="px-2 py-0.5 rounded bg-gray-100 border border-gray-200 text-[11px]">Telecel Cash</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>
@endsection
