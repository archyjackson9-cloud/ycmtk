@extends('layouts.guest')

@section('title', 'Track Your Harvest Order — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <div class="h-12 w-12 mx-auto mb-3 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center shadow-2xs">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Track Your Order</h1>
        <p class="text-xs text-gray-500 mt-1">Enter your order reference code and the phone number used at checkout.</p>
    </div>

    <form action="{{ route('orders.track') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Order Number</label>
            <input type="text" name="order_number" placeholder="e.g. CYM-20260101-ABCDE" required
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 uppercase font-mono font-bold tracking-wider focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Phone Number</label>
            <input type="tel" name="phone" placeholder="024 XXX XXXX" required
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-xs py-3 px-6 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Locate Harvest Order</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-xs text-gray-500">
            Have an account? 
            <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Log in to view all orders</a>
        </p>
    </div>
@endsection
