@extends('layouts.guest')

@section('title', 'Pay by Transfer — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-brand-100 text-brand-900 text-[11px] font-extrabold tracking-wider uppercase mb-3 border border-brand-300/60 shadow-2xs">
            Order Placed
        </span>
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Complete Your Payment</h1>
        <p class="text-xs text-gray-500 mt-1">Send the amount below, quoting your order reference, and we will confirm it on our end.</p>
    </div>

    @if (session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-2xl px-4 py-3 mb-5">
            {{ session('success') }}
        </div>
    @endif

    <div class="liquid-input rounded-2xl p-5 text-xs mb-6 space-y-2.5">
        <div class="flex justify-between items-center text-gray-600">
            <span>Order Number</span>
            <span class="font-bold text-gray-900 font-mono">#{{ $payment->order->order_number }}</span>
        </div>
        <div class="flex justify-between items-center text-gray-600">
            <span>Amount to Pay</span>
            <span class="font-extrabold text-sm text-brand-900">{{ config('cymarket.currency_symbol') }}{{ number_format($payment->amount, 2) }}</span>
        </div>
        <div class="flex justify-between items-center text-gray-500 pt-2 border-t border-gray-200">
            <span>Payment Reference</span>
            <span class="font-mono text-[10px] text-gray-400 truncate max-w-[180px]">{{ $payment->reference }}</span>
        </div>
    </div>

    <div class="rounded-2xl bg-gradient-to-br from-brand-900 to-[#072d1a] p-5 text-white space-y-3 mb-6">
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-accent-400">{{ $network }} Account</h2>
        <div>
            <p class="text-[11px] text-emerald-100/70">Account Name</p>
            <p class="text-sm font-black">{{ $accountName }}</p>
        </div>
        <div>
            <p class="text-[11px] text-emerald-100/70">Account Number</p>
            <p class="text-lg font-black font-mono tracking-wide">{{ $accountNumber }}</p>
        </div>
        <div class="pt-2 border-t border-white/10">
            <p class="text-[11px] text-emerald-100/70">Use as reference / description</p>
            <p class="text-sm font-black font-mono">{{ $payment->order->order_number }}</p>
        </div>
    </div>

    <p class="text-[11px] text-gray-500 leading-relaxed mb-6">{{ $instructions }}</p>

    <form action="{{ route('checkout.manual-pay.proof', $payment->reference) }}" method="POST" enctype="multipart/form-data" class="mb-6">
        @csrf
        <label class="block text-xs font-bold text-gray-700 mb-1.5">Upload proof of payment (optional)</label>
        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf"
               class="w-full text-xs rounded-2xl liquid-input px-3.5 py-2.5 focus:outline-none">
        @error('proof')
            <p class="text-[11px] text-rose-500 mt-1">{{ $message }}</p>
        @enderror
        @if($payment->proof_path)
            <p class="text-[11px] text-emerald-600 mt-1.5">A proof file is already attached - uploading another replaces it.</p>
        @endif
        <button type="submit"
                class="mt-3 w-full border border-brand-300 text-brand-800 hover:bg-brand-50 font-bold rounded-full py-2.5 text-xs transition-colors shadow-2xs">
            Attach Proof
        </button>
    </form>

    <a href="{{ route('orders.show', $payment->order) }}"
       class="block text-center bg-brand-600 hover:bg-brand-700 text-white font-extrabold rounded-full py-3 text-xs shadow-md shadow-brand-600/25 transition-all">
        View Order Status →
    </a>
@endsection
