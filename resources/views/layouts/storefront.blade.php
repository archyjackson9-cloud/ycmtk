<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CY-Market') — Farm-Fresh Produce, Delivered</title>
    <meta name="description" content="CY-Market — farm-grown produce and provisions delivered fresh across Tarkwa and surrounding communities.">

    {{-- PWA: installable storefront, see public/sw.js --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#14532d">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="CY-Market">

    <!-- Preconnect Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full flex flex-col liquid-body-mesh text-gray-900 antialiased font-sans selection:bg-brand-100 selection:text-brand-900">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:bg-brand-900 focus:text-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow-lg">Skip to content</a>

    {{-- Top utility bar --}}
    <div class="bg-gradient-to-r from-brand-950/95 via-brand-900/90 to-brand-950/95 backdrop-blur-md text-emerald-100/90 text-xs border-b border-brand-800/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-brand-500/20 text-brand-200 text-[11px] font-medium border border-brand-400/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Farm-Direct
                </span>
                <span class="hidden sm:inline text-emerald-200/80">Serving {{ config('cymarket.pilot_zone_name') }} with same-day dispatch</span>
            </div>
            <div class="flex items-center gap-5 text-[11px]">
                <a href="{{ route('orders.track-form') }}" class="flex items-center gap-1 text-emerald-200/90 hover:text-white transition-colors">
                    <svg class="w-3.5 h-3.5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Track Order</span>
                </a>
                @auth
                    <a href="{{ route('orders.index') }}" class="text-emerald-200/90 hover:text-white transition-colors">My Orders</a>
                @endauth
                <div class="hidden sm:flex items-center gap-1.5 text-emerald-300/80">
                    <span class="text-accent-400 font-semibold">Secure</span> Pay-by-Transfer
                </div>
            </div>
        </div>
    </div>

    {{-- Main Glass Sticky Header — floating "Liquid Glass" bar --}}
    <header class="sticky top-0 z-40 px-3 sm:px-6 pt-3 transition-all">
        <div class="max-w-7xl mx-auto liquid-nav rounded-[28px] overflow-hidden">
        <div class="px-4 sm:px-6 py-3 flex items-center gap-4 sm:gap-6">
            {{-- Brand Logo --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 shrink-0 group">
                <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 p-0.5 shadow-md shadow-brand-900/10 group-hover:scale-105 transition-transform">
                    <div class="h-full w-full rounded-[10px] bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 flex items-center justify-center text-white font-black text-sm tracking-tight border border-white/20">
                        CY
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center gap-1 leading-none">
                        <span class="text-lg font-extrabold text-gray-900 tracking-tight">CY<span class="text-brand-600">Market</span></span>
                    </div>
                    <span class="text-[10px] font-medium text-gray-400 tracking-wider uppercase mt-0.5">Farm Fresh • Direct</span>
                </div>
            </a>

            {{-- Search Bar --}}
            <form action="{{ route('products.index') }}" method="GET" class="flex-1 max-w-xl mx-auto">
                <div class="relative group">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search fresh vegetables, fruits, grains, tubers..."
                           class="w-full rounded-full liquid-input py-2.5 pl-10 pr-12 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none transition-all">
                    <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand-600 transition-colors pointer-events-none">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                    </div>
                    <button type="submit" aria-label="Search" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-full bg-brand-600 p-1.5 text-white hover:bg-brand-700 active:scale-95 transition-all shadow-xs">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
            </form>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-3 shrink-0 text-sm">
                @auth
                    {{-- Notification Bell - order-tracking updates for registered customers --}}
                    <div class="relative">
                        <button type="button" id="notif-bell-toggle" aria-label="Notifications" aria-expanded="false"
                                class="relative inline-flex items-center justify-center h-9 w-9 rounded-full hover:bg-gray-100 text-gray-600 transition-colors">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if(($unreadNotificationCount ?? 0) > 0)
                                <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-[10px] font-bold text-white shadow-xs">
                                    {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                                </span>
                            @endif
                        </button>
                        <div id="notif-bell-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] liquid-glass rounded-2xl shadow-lg overflow-hidden z-50">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                <span class="text-xs font-extrabold uppercase tracking-wider text-gray-700">Notifications</span>
                                @if(($unreadNotificationCount ?? 0) > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-[11px] font-bold text-brand-700 hover:text-brand-900">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                                @forelse(($recentNotifications ?? []) as $notification)
                                    <a href="{{ route('notifications.open', $notification->id) }}" class="block px-4 py-3 text-xs hover:bg-gray-50 transition-colors {{ $notification->read_at ? '' : 'bg-brand-50/50' }}">
                                        <p class="font-bold text-gray-900">{{ $notification->data['title'] ?? 'Update' }}</p>
                                        <p class="text-gray-500 mt-0.5">{{ $notification->data['message'] ?? '' }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                    </a>
                                @empty
                                    <p class="px-4 py-6 text-xs text-gray-400 text-center">No notifications yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('account.edit') }}" class="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 rounded-full hover:bg-gray-100 text-gray-700 font-medium transition-colors">
                        <span class="h-6 w-6 rounded-full bg-brand-100 text-brand-800 flex items-center justify-center text-xs font-bold">
                            {{ Str::substr(auth()->user()->name, 0, 1) }}
                        </span>
                        <span>{{ Str::of(auth()->user()->name)->before(' ') }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-gray-500 hover:text-red-600 transition-colors px-2 py-1">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center text-sm font-semibold text-gray-700 hover:text-brand-700 px-3 py-1.5 transition-colors">Log in</a>
                    <a href="{{ route('register') }}" class="hidden sm:inline-flex items-center text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 px-4 py-2 rounded-full shadow-xs hover:shadow-md hover:shadow-brand-600/20 liquid-tap transition-all">Sign up</a>
                @endauth

                {{-- Glass Cart Pill --}}
                <a href="{{ route('cart.index') }}" class="relative inline-flex items-center gap-2 rounded-full liquid-glass hover:bg-white/10 px-3.5 py-2 text-brand-900 liquid-tap transition-all group">
                    <svg class="h-5 w-5 text-brand-700 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span class="text-xs font-bold text-brand-900 hidden sm:inline">Cart</span>
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-brand-600 text-[11px] font-bold text-white shadow-xs" id="cart-count">
                        {{ $cartItemCount ?? 0 }}
                    </span>
                </a>
            </div>
        </div>

        {{-- Category Navigation Rail --}}
        <nav class="border-t border-white/50">
            <div class="px-4 sm:px-6">
                <ul class="flex items-center gap-2 overflow-x-auto text-xs font-semibold py-2.5 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                    <li>
                        <a href="{{ route('products.index') }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full {{ request()->routeIs('products.index') && !request('category') ? 'bg-brand-600 text-white shadow-xs' : 'bg-gray-100/70 text-gray-700 hover:bg-brand-50 hover:text-brand-700' }} transition-colors whitespace-nowrap">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            All Produce
                        </a>
                    </li>
                    @foreach(($megaMenuCategories ?? []) as $category)
                        <li>
                            <a href="{{ route('categories.show', $category) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-full text-gray-600 hover:text-brand-800 hover:bg-brand-50/80 transition-colors whitespace-nowrap {{ request()->is('categories/'.$category->slug) ? 'bg-brand-50 text-brand-800 font-bold border border-brand-200/60' : '' }}">
                                {{ $category->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
        </div>
    </header>

    @include('partials.flash-messages')

    <main id="main-content" class="flex-1">
        @yield('content')
    </main>

    {{-- Modern Footer --}}
    <footer class="bg-gradient-to-b from-brand-950 via-[#062013] to-[#03130b] text-emerald-100/80 mt-16 border-t border-brand-900/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14 grid grid-cols-1 md:grid-cols-4 gap-10 text-sm">
            {{-- Col 1: Brand & Pilot Zone --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2.5">
                    <div class="h-9 w-9 rounded-xl bg-brand-600 flex items-center justify-center text-white font-black text-sm border border-brand-400/30 shadow-md">
                        CY
                    </div>
                    <span class="text-xl font-black text-white tracking-tight">CY<span class="text-emerald-400">Market</span></span>
                </div>
                <p class="text-emerald-200/70 text-xs leading-relaxed">
                    Farm-grown harvest, sold direct from our farms to your doorstep. Guaranteed 100% single-seller authenticity — zero middlemen, maximum freshness.
                </p>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-brand-900/80 border border-brand-700/50 text-xs text-emerald-200">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Pilot Zone: <strong>{{ config('cymarket.pilot_zone_name') }}</strong></span>
                </div>
            </div>

            {{-- Col 2: Navigation --}}
            <div>
                <h3 class="text-white font-bold text-xs uppercase tracking-wider mb-4">Quick Navigation</h3>
                <ul class="space-y-2 text-xs">
                    <li><a href="{{ route('products.index') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> All Farm Produce</a></li>
                    <li><a href="{{ route('orders.track-form') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> Track Order by SMS Code</a></li>
                    <li><a href="{{ route('cart.index') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> View Shopping Cart</a></li>
                    @auth
                        <li><a href="{{ route('orders.index') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> My Order History</a></li>
                        <li><a href="{{ route('account.edit') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> Account Settings</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="hover:text-white transition-colors flex items-center gap-1.5"><span class="text-emerald-500">›</span> Customer Login</a></li>
                    @endauth
                </ul>
            </div>

            {{-- Col 3: Customer Care & Payments --}}
            <div>
                <h3 class="text-white font-bold text-xs uppercase tracking-wider mb-4">Pay by Transfer</h3>
                <p class="text-xs text-emerald-200/70 leading-relaxed mb-3">
                    Pay our account directly - we confirm it on our end.
                </p>
                <div class="flex flex-wrap gap-2 text-[11px] font-semibold text-emerald-100">
                    <span class="px-2.5 py-1 rounded-md bg-white/10 border border-white/15">Bank / MoMo Transfer</span>
                </div>
                <div class="mt-4 flex items-center gap-2 text-xs text-emerald-300/80">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    <span>Instant automated SMS delivery receipt</span>
                </div>
            </div>

            {{-- Col 4: Freshness Guarantee --}}
            <div>
                <h3 class="text-white font-bold text-xs uppercase tracking-wider mb-4">Freshness Promise</h3>
                <div class="glass-dark-card rounded-2xl p-4 space-y-2 border border-white/10">
                    <div class="flex items-center gap-2 text-accent-400 font-bold text-xs">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        100% Quality Protected
                    </div>
                    <p class="text-[11px] text-emerald-200/80 leading-relaxed">
                        Every order is inspected before dispatch. If anything does not meet farm-grade freshness, we issue an immediate replacement or full refund.
                    </p>
                </div>
            </div>
        </div>

        <div class="border-t border-brand-900/60 py-6 text-center text-xs text-emerald-300/60">
            <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p>&copy; {{ now()->year }} CY-Market. Grown with care. All rights reserved.</p>
                <p class="text-[11px]">Designed for exceptional farm-to-table commerce.</p>
            </div>
        </div>
    </footer>

    @auth
        {{-- Notification bell toggle (no Alpine on the storefront) --}}
        <script>
            (function () {
                var toggle = document.getElementById('notif-bell-toggle');
                var panel = document.getElementById('notif-bell-panel');
                if (!toggle || !panel) return;

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var hidden = panel.classList.toggle('hidden');
                    toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                });

                document.addEventListener('click', function (e) {
                    if (!panel.contains(e.target) && e.target !== toggle) {
                        panel.classList.add('hidden');
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            })();
        </script>
    @endauth

    {{-- PWA install prompt - Chrome/Android/Desktop only; iOS has no
         programmatic prompt, but the manifest/icons above already make
         "Add to Home Screen" from Safari's share sheet work correctly. --}}
    <div id="pwa-install-banner" class="hidden fixed bottom-4 inset-x-4 sm:inset-x-auto sm:right-4 sm:left-auto sm:w-80 z-50 liquid-glass rounded-2xl shadow-lg p-4 flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 flex items-center justify-center text-white font-black text-xs shrink-0">CY</div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-extrabold text-gray-900">Install CY-Market</p>
            <p class="text-[11px] text-gray-500">Add to your home screen for quick access.</p>
        </div>
        <button type="button" id="pwa-install-dismiss" aria-label="Dismiss" class="text-gray-400 hover:text-gray-600 text-sm px-1">✕</button>
        <button type="button" id="pwa-install-accept" class="bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-full px-3 py-2 shrink-0 transition-colors">Install</button>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {
                    // Registration can fail harmlessly (e.g. no HTTPS in local dev) - the site still works normally.
                });
            });
        }

        (function () {
            var DISMISS_KEY = 'cymarket_pwa_install_dismissed';
            var banner = document.getElementById('pwa-install-banner');
            var deferredPrompt = null;

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferredPrompt = e;

                if (banner && localStorage.getItem(DISMISS_KEY) !== '1') {
                    banner.classList.remove('hidden');
                }
            });

            document.getElementById('pwa-install-dismiss')?.addEventListener('click', function () {
                banner.classList.add('hidden');
                try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) {}
            });

            document.getElementById('pwa-install-accept')?.addEventListener('click', function () {
                banner.classList.add('hidden');
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt = null;
                }
            });

            window.addEventListener('appinstalled', function () {
                if (banner) banner.classList.add('hidden');
                try { localStorage.setItem(DISMISS_KEY, '1'); } catch (e) {}
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
