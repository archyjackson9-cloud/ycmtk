@extends('layouts.guest')

@section('title', 'Simulate MTN MoMo Payment — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-900 text-[11px] font-extrabold tracking-wider uppercase mb-3 border border-amber-300/60 shadow-2xs">
            <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
            MoMo Simulation Mode
        </span>
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Simulate Mobile Money</h1>
        <p class="text-xs text-gray-500 mt-1">This simulation allows testing the real-time order confirmation, stock debit, and SMS trigger without actual mobile money deduction.</p>
    </div>

    <div class="liquid-input rounded-2xl p-5 text-xs mb-6 space-y-2.5">
        <div class="flex justify-between items-center text-gray-600">
            <span>Order Number</span>
            <span class="font-bold text-gray-900 font-mono">#{{ $payment->order->order_number }}</span>
        </div>
        <div class="flex justify-between items-center text-gray-600">
            <span>Amount Due</span>
            <span class="font-extrabold text-sm text-brand-900">{{ config('cymarket.currency_symbol') }}{{ number_format($payment->amount, 2) }}</span>
        </div>
        <div class="flex justify-between items-center text-gray-500 pt-2 border-t border-gray-200">
            <span>Gateway Ref</span>
            <span class="font-mono text-[10px] text-gray-400 truncate max-w-[180px]">{{ $payment->reference }}</span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <form action="{{ route('checkout.sandbox-confirm', $payment->reference) }}" method="POST">
            @csrf
            <input type="hidden" name="approve" value="0">
            <button type="submit" 
                    class="w-full border border-rose-300 text-rose-700 hover:bg-rose-50 font-bold rounded-full py-3 text-xs transition-colors shadow-2xs">
                Simulate Decline
            </button>
        </form>
        <form action="{{ route('checkout.sandbox-confirm', $payment->reference) }}" method="POST">
            @csrf
            <input type="hidden" name="approve" value="1">
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold rounded-full py-3 text-xs shadow-md shadow-brand-600/25 transition-all">
                Approve Payment ✓
            </button>
        </form>
    </div>
@endsection
