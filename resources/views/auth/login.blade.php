@extends('layouts.guest')

@section('title', 'Log In — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Welcome Back</h1>
        <p class="text-xs text-gray-500 mt-1">Log in to track orders and manage your delivery addresses</p>
    </div>

    <form action="{{ route('login') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Email Address</label>
            <div class="relative">
                <input type="email" name="email" value="{{ old('email') }}" required autofocus 
                       placeholder="you@example.com"
                       class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="block text-xs font-bold text-gray-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-[11px] font-bold text-brand-700 hover:text-brand-900 transition-colors">Forgot password?</a>
            </div>
            <div class="relative">
                <input type="password" name="password" required 
                       placeholder="••••••••"
                       class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
            </div>
        </div>

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 cursor-pointer">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                <span>Remember me</span>
            </label>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-xs py-3 px-6 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Log In</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-xs text-gray-500">
            New to CY-Market? 
            <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Create an account</a>
        </p>
    </div>
@endsection
