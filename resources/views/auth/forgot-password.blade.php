@extends('layouts.guest')

@section('title', 'Forgot Password — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Reset Password</h1>
        <p class="text-xs text-gray-500 mt-1">Enter your registered email and we'll send you a password recovery link.</p>
    </div>

    <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus 
                   placeholder="you@example.com"
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-xs py-3 px-6 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Send Password Reset Link</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-xs text-gray-500">
            Remembered your password? 
            <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Back to log in</a>
        </p>
    </div>
@endsection
