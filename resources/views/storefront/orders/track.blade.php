@extends('layouts.guest')

@section('title', 'Track Your Harvest Order — CY-Market')

@section('content')
    <div class="text-center mb-4 sm:mb-6">
        <div class="h-12 w-12 mx-auto mb-3 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center shadow-2xs">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Track Your Order</h1>
        <p class="text-xs text-gray-500 mt-1">Enter your order reference code and the phone number used at checkout.</p>
    </div>

    <form action="{{ route('orders.track') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label for="order_number" class="block text-xs font-bold text-gray-700 mb-1.5">Order Number</label>
            <input type="text" id="order_number" name="order_number" value="{{ old('order_number') }}" placeholder="e.g. CYM-20260101-ABCDE" required autocomplete="off" autocapitalize="characters" spellcheck="false"
                   @error('order_number') aria-invalid="true" aria-describedby="order_number-error" @enderror
                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 uppercase font-mono font-bold tracking-wider focus:outline-none">
            @error('order_number')<p id="order_number-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="block text-xs font-bold text-gray-700 mb-1.5">Phone Number</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="024 XXX XXXX" required autocomplete="tel" inputmode="tel"
                   @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
            @error('phone')<p id="phone-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-sm py-3 px-6 min-h-11 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Locate Harvest Order</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-sm sm:text-xs text-gray-600">
            Have an account? 
            <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Log in to view all orders</a>
        </p>
    </div>
@endsection
