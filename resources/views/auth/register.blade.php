@extends('layouts.guest')

@section('title', 'Create an Account — CY-Market')

@section('content')
    <div class="text-center mb-4 sm:mb-6">
        <h1 class="text-xl font-black text-gray-900 tracking-tight">Join CY-Market</h1>
        <p class="text-xs text-gray-500 mt-1">Direct farm harvest delivery right to your door</p>
    </div>

    <form action="{{ route('register') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-xs font-bold text-gray-700 mb-1.5">Full Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   placeholder="e.g. Abena Mensah" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
            @error('name')<p id="name-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="block text-xs font-bold text-gray-700 mb-1.5">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false"
                   placeholder="abena@example.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
            @error('email')<p id="email-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="block text-xs font-bold text-gray-700 mb-1.5">Phone (for SMS delivery alerts)</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel"
                   placeholder="024 XXX XXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                   class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
            @error('phone')<p id="phone-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label for="password" class="block text-xs font-bold text-gray-700 mb-1.5">Password</label>
                <input type="password" id="password" name="password" required autocomplete="new-password"
                       placeholder="••••••••" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
                @error('password')<p id="password-error" role="alert" class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-gray-700 mb-1.5">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       placeholder="••••••••"
                       class="w-full rounded-2xl liquid-input text-sm px-3.5 py-3 sm:py-2.5 focus:outline-none">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" 
                    class="w-full inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 text-white font-extrabold text-sm py-3 px-6 min-h-11 rounded-full shadow-md shadow-brand-600/25 hover:shadow-lg hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all">
                <span>Create Customer Account</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>

    <div class="pt-4 text-center border-t border-gray-100">
        <p class="text-sm sm:text-xs text-gray-600">
            Already have an account? 
            <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:text-brand-900 transition-colors">Log in</a>
        </p>
    </div>
@endsection
