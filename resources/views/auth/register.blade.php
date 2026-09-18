@extends('layouts.guest')

@section('title', 'Create an Account — CY-Market')

@section('content')
    <div class="text-center mb-6">
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Join CY-Market</h1>
        <p class="text-xs text-gray-500 mt-1">Direct farm harvest delivery right to your door</p>
    </div>

    <form action="{{ route('register') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus 
                   placeholder="e.g. Abena Mensah"
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required 
                   placeholder="abena@example.com"
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 mb-1.5">Phone (for Real-Time SMS Dispatch Alerts)</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" 
                   placeholder="024 XXX XXXX"
                   class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Password</label>
                <input type="password" name="password" required 
                       placeholder="••••••••"
                       class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">Confirm</label>
                <input type="password" name="password_confirmation" required 
                       placeholder="••••••••"
                       class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-xs py-3 px-6 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Create Customer Account</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-xs text-gray-500">
            Already have an account? 
            <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Log in</a>
        </p>
    </div>
@endsection
