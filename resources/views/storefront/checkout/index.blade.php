@extends('layouts.storefront')

@section('title', 'Secure Checkout — CY-Market')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 sm:py-10 pb-24 lg:pb-10">

    {{-- Modern Step Indicator --}}
    <div class="max-w-xl mx-auto mb-5 sm:mb-10">
        <ol class="flex items-center justify-between" aria-label="Checkout progress">
            <li class="flex items-center gap-2">
                <span class="h-8 w-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-sm">✓</span>
                <span class="text-xs font-bold text-gray-900">Cart</span>
            </li>
            <li class="h-0.5 flex-1 mx-2 sm:mx-4 bg-emerald-500" aria-hidden="true"></li>
            <li class="flex items-center gap-2" aria-current="step">
                <span class="h-8 w-8 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-black shadow-md shadow-brand-600/30 ring-4 ring-brand-500/20">2</span>
                <span class="text-xs font-bold text-brand-900">Delivery & Pay</span>
            </li>
            <li class="h-0.5 flex-1 mx-2 sm:mx-4 bg-gray-200" aria-hidden="true"></li>
            <li class="flex items-center gap-2 text-gray-500">
                <span class="h-8 w-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center text-xs font-bold">3</span>
                <span class="text-xs font-semibold"><span class="sm:hidden">Done</span><span class="hidden sm:inline">Confirmation</span></span>
            </li>
        </ol>
    </div>

    <form action="{{ route('checkout.store') }}" method="POST" class="grid lg:grid-cols-12 gap-4 sm:gap-8 items-start" id="checkout-form">
        @csrf

        {{-- Left Form Column --}}
        <div class="lg:col-span-8 space-y-4 sm:space-y-6 min-w-0">

            {{-- Guest Notice --}}
            @guest
                <div class="liquid-glass rounded-2xl sm:rounded-[28px] p-4 sm:p-6 shadow-xs space-y-3 sm:space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h2 class="text-[13px] sm:text-sm font-extrabold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Customer Contact Details
                        </h2>
                        <a href="{{ route('login') }}" class="text-xs font-bold text-brand-700 hover:underline py-2 shrink-0">Log in instead</a>
                    </div>
                    <p class="text-xs text-gray-500">
                        Checking out as a guest. We use your phone number for real-time SMS harvest dispatch updates.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-3 sm:gap-4">
                        <div>
                            <label for="guest_name" class="block text-xs font-bold text-gray-700 mb-1.5">Full name <span class="text-rose-500">*</span></label>
                            <input type="text" id="guest_name" name="guest_name" value="{{ old('guest_name') }}" required autocomplete="name"
                                   placeholder="e.g. Kwame Mensah" @error('guest_name') aria-invalid="true" aria-describedby="guest_name-error" @enderror
                                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                            @error('guest_name')<p id="guest_name-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="guest_email" class="block text-xs font-bold text-gray-700 mb-1.5">Email</label>
                            <input type="email" id="guest_email" name="guest_email" value="{{ old('guest_email') }}" required autocomplete="email" inputmode="email"
                                   placeholder="kwame@example.com" @error('guest_email') aria-invalid="true" aria-describedby="guest_email-error" @enderror
                                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                            @error('guest_email')<p id="guest_email-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            @endguest

            {{-- Delivery Details Card --}}
            <div class="liquid-glass rounded-2xl sm:rounded-[28px] p-4 sm:p-6 shadow-xs space-y-3 sm:space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">
                        Delivery Destination
                    </h2>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <label for="recipient_name" class="block text-xs font-bold text-gray-700 mb-1.5">Recipient Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="recipient_name" autocomplete="name" name="recipient_name" value="{{ old('recipient_name', $defaultAddress->recipient_name ?? auth()->user()?->name) }}" required
                               placeholder="Full name of person receiving package" @error('recipient_name') aria-invalid="true" aria-describedby="recipient_name-error" @enderror
                               class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                        @error('recipient_name')<p id="recipient_name-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-bold text-gray-700 mb-1.5">Phone Number (delivery / SMS) <span class="text-rose-500">*</span></label>
                        <input type="tel" id="phone" inputmode="tel" autocomplete="tel" name="phone" value="{{ old('phone', $defaultAddress->phone ?? auth()->user()?->phone) }}" required
                               placeholder="024 XXX XXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                               class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                        @error('phone')<p id="phone-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    {{-- Delivery Zone Selector --}}
                    <div class="sm:col-span-2">
                        <label for="delivery_zone_id" class="block text-xs font-bold text-gray-700 mb-1.5">Delivery Zone</label>
                        <div class="relative">
                            <select name="delivery_zone_id" id="delivery_zone_id"
                                    class="w-full appearance-none rounded-2xl liquid-input text-sm font-medium text-gray-900 px-3.5 py-3 sm:py-2.5 pr-10 truncate focus:outline-none cursor-pointer">
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
                        <p class="text-[11px] text-gray-500 mt-1">Pick the zone closest to you, or skip this and pin your exact location below instead.</p>
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
                        <label for="address_line" class="block text-xs font-bold text-gray-700 mb-1.5">Physical Address / House No. / Street <span class="text-rose-500">*</span></label>
                        <textarea name="address_line" id="address_line" rows="2" required autocomplete="street-address"
                                  placeholder="Detailed address or location description..." @error('address_line') aria-invalid="true" aria-describedby="address_line-error" @enderror
                                  class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">{{ old('address_line', $defaultAddress->address_line ?? '') }}</textarea>
                        @error('address_line')<p id="address_line-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    {{-- Landmark --}}
                    <div class="sm:col-span-2">
                        <label for="landmark" class="block text-xs font-bold text-gray-700 mb-1.5">Prominent Landmark (Optional)</label>
                        <input type="text" id="landmark" name="landmark" value="{{ old('landmark', $defaultAddress->landmark ?? '') }}"
                               placeholder="e.g. Near Tarkwa UMaT Main Gate, opposite Shell station"
                               class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                    </div>

                    {{-- Notes --}}
                    <div class="sm:col-span-2">
                        <label for="notes" class="block text-xs font-bold text-gray-700 mb-1.5">Special Harvest Packing / Delivery Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="2"
                                  placeholder="e.g. Please call before arrival or leave with security"
                                  class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Promo Code Card --}}
            <div class="liquid-glass rounded-2xl sm:rounded-[28px] p-4 sm:p-6 shadow-xs space-y-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">Promo Code / Voucher</h2>
                </div>
                <div class="relative">
                    <label for="coupon_code" class="sr-only">Promo code</label>
                    <input type="text" id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}" placeholder="Enter promo code" autocomplete="off" autocapitalize="characters"
                           @error('coupon_code') aria-invalid="true" aria-describedby="coupon_code-error" @enderror
                           class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none uppercase font-semibold">
                    @error('coupon_code')<p id="coupon_code-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Payment Card --}}
            <div class="liquid-glass rounded-2xl sm:rounded-[28px] p-4 sm:p-6 shadow-xs space-y-3 sm:space-y-4">
                <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">Payment</h2>
                </div>

                @if($manualPaymentAvailable)
                    <div class="rounded-2xl sm:rounded-3xl bg-gradient-to-br from-brand-900 to-[#072d1a] border border-brand-800 p-4 sm:p-6 text-white shadow-md space-y-2">
                        <div class="flex items-center gap-2 text-accent-400 font-bold text-xs uppercase tracking-wider">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Pay by Transfer</span>
                        </div>
                        <h3 class="text-lg font-black text-white">{{ $network }}</h3>
                        <p class="text-xs text-emerald-100/80 leading-relaxed">
                            Place your order and we will show you the account to pay ({{ $accountName }}) along with your order reference to quote. We confirm payment on our end once it lands.
                        </p>
                    </div>
                @else
                    {{-- Only shown if a Super Admin hasn't set up Admin > Settings > "Pay by Transfer" yet. --}}
                    <div class="rounded-2xl sm:rounded-3xl bg-amber-50 border border-amber-200 p-4 sm:p-6 text-amber-900 text-xs leading-relaxed">
                        Checkout isn't available yet - payment details haven't been configured. Please check back shortly.
                    </div>
                @endif
            </div>

        </div>

        {{-- Right Order Summary Sticky Card --}}
        <div class="lg:col-span-4 lg:sticky lg:top-28 space-y-4 min-w-0">
            <div class="glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-md space-y-3 sm:space-y-5">
                <h2 class="text-base font-black text-gray-900 tracking-tight pb-2 sm:pb-3 border-b border-gray-100">
                    Order Summary
                </h2>

                {{-- Scrollable List of Items --}}
                <ul class="space-y-2.5 sm:space-y-3 max-h-48 sm:max-h-64 overflow-y-auto pr-1 divide-y divide-gray-100">
                    @foreach($cart->items as $item)
                        <li class="flex items-center justify-between text-xs pt-3 first:pt-0">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="font-bold text-gray-900 line-clamp-2 break-words">{{ $item->product->name }}</p>
                                <p class="text-gray-500 text-[11px]">{{ $item->quantity }} × {{ config('cymarket.currency_symbol') }}{{ number_format($item->unit_price, 2) }}</p>
                            </div>
                            <span class="font-black text-gray-900 shrink-0">{{ config('cymarket.currency_symbol') }}{{ number_format($item->lineTotal(), 2) }}</span>
                        </li>
                    @endforeach
                </ul>

                {{-- Breakdown --}}
                <div class="border-t border-gray-100 pt-3 space-y-2 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Items Subtotal</span>
                        <span class="font-black text-gray-900 text-sm" id="items-subtotal">{{ config('cymarket.currency_symbol') }}{{ number_format($cart->subtotal(), 2) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 text-gray-500">
                        <span>Delivery Zone Fee</span>
                        <span class="font-semibold text-emerald-700 text-right">Added on confirmation</span>
                    </div>
                </div>

                <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-200/60 text-[11px] text-amber-900 font-semibold">
                    {{ app(\App\Services\DeliveryScheduleService::class)->noticeLabel() }}
                </div>

                {{-- Pay CTA Button --}}
                <div class="pt-2">
                    <button type="submit" {{ $manualPaymentAvailable ? '' : 'disabled' }}
                            class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-sm py-3.5 sm:py-4 px-6 min-h-12 rounded-full shadow-lg shadow-brand-600/30 hover:shadow-xl hover:shadow-brand-600/40 hover:-translate-y-0.5 active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0">
                        <span>Place Order</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

                <div class="text-center pt-2">
                    <p class="text-[11px] text-gray-500 flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                        <span>Secure Checkout</span>
                    </p>
                </div>
            </div>
        </div>

        {{-- Phone-only bottom bar: subtotal stays visible and the primary action is always one tap away --}}
        <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 bg-white/95 border-t border-gray-200 px-4 py-2.5 flex items-center gap-3" style="padding-bottom: max(0.625rem, env(safe-area-inset-bottom))">
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 leading-none">Items total</p>
                <p class="text-base font-black text-gray-900 leading-tight">{{ config('cymarket.currency_symbol') }}{{ number_format($cart->subtotal(), 2) }}</p>
            </div>
            <button type="submit" {{ $manualPaymentAvailable ? '' : 'disabled' }}
                    class="ml-auto inline-flex items-center justify-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-extrabold text-sm py-3 px-6 min-h-11 rounded-full shadow-md active:scale-[0.98] transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                Place Order
            </button>
        </div>

    </form>
</div>
@endsection

@push('scripts')
<script>
    // Move focus to the first invalid field after a failed submit, and stop double submits on slow connections.
    (function () {
        var bad = document.querySelector('#checkout-form [aria-invalid="true"]');
        if (bad) { bad.focus({ preventScroll: false }); }
        var form = document.getElementById('checkout-form');
        form && form.addEventListener('submit', function () {
            form.querySelectorAll('button[type=submit]').forEach(function (b) {
                setTimeout(function () { b.disabled = true; b.classList.add('opacity-60'); }, 0);
            });
        });
    })();
</script>
@endpush
