<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CY-Market') — Farm-Fresh Direct</title>
    
    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gradient-to-br from-[#062013] via-[#093520] to-[#04170d] text-gray-900 antialiased font-sans relative overflow-x-hidden flex flex-col justify-center">

    {{-- Ambient Background Glow Orbs --}}
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[40rem] h-[25rem] bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 right-10 w-[30rem] h-[20rem] bg-accent-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="min-h-full flex flex-col items-center justify-center px-4 py-12 relative z-10">
        {{-- Brand Header --}}
        <a href="{{ route('home') }}" class="mb-8 flex items-center gap-3 group">
            <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 flex items-center justify-center text-white font-black text-base shadow-lg shadow-brand-500/25 group-hover:scale-105 transition-transform border border-white/20">
                CY
            </div>
            <div class="flex flex-col">
                <span class="text-2xl font-black text-white tracking-tight leading-none">CY<span class="text-emerald-400">Market</span></span>
                <span class="text-[10px] uppercase font-bold text-emerald-300/80 tracking-widest mt-1">Farm Direct Produce</span>
            </div>
        </a>

        {{-- Glass Card Container --}}
        <div class="w-full max-w-md liquid-glass-strong rounded-[32px] shadow-2xl shadow-black/30 p-8 sm:p-10 space-y-6">
            @include('partials.flash-messages')
            @yield('content')
        </div>

        {{-- Return to storefront --}}
        <div class="mt-8 text-center">
            <a href="{{ route('home') }}" class="text-xs font-semibold text-emerald-200/80 hover:text-white transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back to CY-Market storefront</span>
            </a>
        </div>
    </div>
</body>
</html>
