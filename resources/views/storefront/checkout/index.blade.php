@extends('layouts.storefront')

@section('title', 'Secure Checkout — CY-Market')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">

    {{-- Modern Step Indicator --}}
    <div class="max-w-xl mx-auto mb-10">
        <ol class="flex items-center justify-between">
            <li class="flex items-center gap-2">
                <span class="h-8 w-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-sm">✓</span>
                <span class="text-xs font-bold text-gray-900">Cart</span>
            </li>
            <li class="h-0.5 flex-1 mx-4 bg-emerald-500"></li>
            <li class="flex items-center gap-2">
                <span class="h-8 w-8 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-black shadow-md shadow-brand-600/30 ring-4 ring-brand-500/20">2</span>
                <span class="text-xs font-bold text-brand-900">Delivery & Pay</span>
            </li>
            <li class="h-0.5 flex-1 mx-4 bg-gray-200"></li>
            <li class="flex items-center gap-2 text-gray-400">
                <span class="h-8 w-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-bold">3</span>
                <span class="text-xs font-semibold">Confirmation</span>
            </li>
        </ol>
    </div>

    <form action="{{ route('checkout.store') }}" method="POST" class="grid lg:grid-cols-12 gap-8 items-start">
        @csrf

        {{-- Left Form Column --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Guest Notice --}}
            @guest
                <div class="liquid-glass rounded-[28px] p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Customer Contact Details
                        </h2>
                        <a href="{{ route('login') }}" class="text-xs font-bold text-brand-700 hover:underline">Log in instead</a>
                    </div>
                    <p class="text-xs text-gray-500">
                        Checking out as a guest. We use your phone number for real-time SMS harvest dispatch updates.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Full name <span class="text-rose-500">*</span></label>
                            <input type="text" name="guest_name" value="{{ old('guest_name') }}" required
                                   placeholder="e.g. Kwame Mensah"
                                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1.5">Email (optional)</label>
                            <input type="email" name="guest_email" value="{{ old('guest_email') }}"
                                   placeholder="kwame@example.com"
                                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                        </div>
                    </div>
                </div>
            @endguest

            {{-- Delivery Details Card --}}
            <div class="liquid-glass rounded-[28px] p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">
                        Delivery Destination
                    </h2>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Recipient Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="recipient_name" value="{{ old('recipient_name', $defaultAddress->recipient_name ?? auth()->user()?->name) }}" required
                               placeholder="Full name of person receiving package"
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Phone Number (MoMo / SMS) <span class="text-rose-500">*</span></label>
                        <input type="tel" name="phone" value="{{ old('phone', $defaultAddress->phone ?? auth()->user()?->phone) }}" required
                               placeholder="024 XXX XXXX"
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>

                    {{-- Delivery Zone Selector --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Delivery Zone</label>
                        <div class="relative">
                            <select name="delivery_zone_id"
                                    class="w-full appearance-none rounded-2xl liquid-input text-xs font-medium text-gray-900 px-3.5 py-2.5 pr-10 focus:outline-none cursor-pointer">
                                <option value="">Standard Base Zone Rate — {{ config('cymarket.currency_symbol') }}{{ number_format(app(\App\Services\SettingsService::class)->deliveryFee(), 2) }}</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('delivery_zone_id', $defaultAddress->delivery_zone_id ?? '') == $zone->id ? 'selected' : '' }}>
                                        {{ $zone->name }} — {{ config('cymarket.currency_symbol') }}{{ number_format($zone->fee(), 2) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Pick the zone closest to you, or skip this and pin your exact location below instead.</p>
                    </div>

                    {{-- Geo Location Picker - the alternative to picking a zone above --}}
                    <div class="sm:col-span-2 relative">
                        <div class="flex items-center gap-3 -mt-1 mb-1">
                            <span class="h-px flex-1 bg-gray-200"></span>
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-gray-400">Or</span>
                            <span class="h-px flex-1 bg-gray-200"></span>
                        </div>
                        @include('partials.location-picker', [
                            'mapId' => 'checkout-map',
                            'initialLat' => old('latitude', $defaultAddress->latitude ?? null),
                            'initialLng' => old('longitude', $defaultAddress->longitude ?? null),
                        ])
                    </div>

                    {{-- Address Line --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Physical Address / House No. / Street <span class="text-rose-500">*</span></label>
                        <textarea name="address_line" rows="2" required
                                  placeholder="Detailed address or location description..."
                                  class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">{{ old('address_line', $defaultAddress->address_line ?? '') }}</textarea>
                    </div>

                    {{-- Landmark --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Prominent Landmark (Optional)</label>
                        <input type="text" name="landmark" value="{{ old('landmark', $defaultAddress->landmark ?? '') }}"
                               placeholder="e.g. Near Tarkwa UMaT Main Gate, opposite Shell station"
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>

                    {{-- Notes --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">Special Harvest Packing / Delivery Notes (Optional)</label>
                        <textarea name="notes" rows="2"
                                  placeholder="e.g. Please call before arrival or leave with security"
                                  class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Promo Code Card --}}
            <div class="liquid-glass rounded-[28px] p-6 shadow-xs space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">Promo Code / Voucher</h2>
                </div>
                <div class="relative">
                    <input type="text" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Enter harvest promo code (e.g. HARVEST10)"
                           class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none uppercase font-semibold">
                </div>
            </div>

            {{-- Hubtel Mobile Money Information Card --}}
            <div class="rounded-3xl bg-gradient-to-br from-brand-900 to-[#072d1a] border border-brand-800 p-6 text-white shadow-md space-y-3">
                <div class="flex items-center gap-2 text-accent-400 font-bold text-xs uppercase tracking-wider">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>Instant Secured Mobile Money via Hubtel</span>
                </div>
                <h3 class="text-lg font-black text-white">Protected Payment Guarantee</h3>
                <p class="text-xs text-emerald-100/80 leading-relaxed">
                    You will be securely redirected to Hubtel Mobile Money checkout (supporting MTN MoMo, Telecel Cash, and Bank Cards). Harvest fulfillment starts immediately upon automated payment verification.
                </p>
            </div>

        </div>

        {{-- Right Order Summary Sticky Card --}}
        <div class="lg:col-span-4 sticky top-28 space-y-4">
            <div class="glass-card rounded-3xl p-6 shadow-md space-y-5">
                <h2 class="text-base font-black text-gray-900 tracking-tight pb-3 border-b border-gray-100">
                    Order Summary
                </h2>

                {{-- Scrollable List of Items --}}
                <ul class="space-y-3 max-h-64 overflow-y-auto pr-1 divide-y divide-gray-100">
                    @foreach($cart->items as $item)
                        <li class="flex items-center justify-between text-xs pt-3 first:pt-0">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="font-bold text-gray-900 truncate">{{ $item->product->name }}</p>
                                <p class="text-gray-400 text-[11px]">{{ $item->quantity }} × {{ config('cymarket.currency_symbol') }}{{ number_format($item->unit_price, 2) }}</p>
                            </div>
                            <span class="font-black text-gray-900 shrink-0">{{ config('cymarket.currency_symbol') }}{{ number_format($item->lineTotal(), 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- Breakdown --}}
                <div class="border-t border-gray-100 pt-3 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Items Subtotal</span>
                        <span class="font-bold text-gray-900">{{ config('cymarket.currency_symbol') }}{{ number_format($cart->subtotal(), 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-500">
                        <span>Delivery Zone Fee</span>
                        <span class="font-semibold text-emerald-700">Calculated on confirmation</span>
                    </div>
                </div>

                {{-- Pay CTA Button --}}
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-sm py-4 px-6 rounded-full shadow-lg shadow-brand-600/30 hover:shadow-xl hover:shadow-brand-600/40 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                        <span>Pay with Hubtel Mobile Money</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

                <div class="text-center pt-2">
                    <p class="text-[11px] text-gray-400 flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        <span>256-bit Encrypted Hubtel Transaction</span>
                    </p>
                </div>
            </div>
        </div>

    </form>
</div>
@endsection
