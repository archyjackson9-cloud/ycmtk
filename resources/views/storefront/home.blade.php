@extends('layouts.storefront')

@section('title', 'CY-Market — Farm-Fresh Produce, Direct from our Harvest')

@section('content')

    {{-- Hero Section - editable via Admin > Marketing > Banners. Every live banner is a slide. --}}
    @php($slides = $heroBanners->isEmpty() ? collect([null]) : $heroBanners->values())
    <section id="hero" class="group/hero relative bg-gradient-to-br from-brand-950 via-[#072f1b] to-emerald-950 text-white overflow-hidden"
             aria-roledescription="carousel" aria-label="Featured offers">
        {{-- Background media, one layer per slide, behind everything else --}}
        @foreach($slides as $i => $banner)
            <div data-hero-bg class="absolute inset-0 transition-opacity duration-700 {{ $i === 0 ? '' : 'opacity-0' }}" aria-hidden="true">
                @if($banner?->hasVideo())
                    <video src="{{ $banner->videoUrl() }}" class="absolute inset-0 w-full h-full object-cover" muted loop playsinline
                           preload="none" data-hero-video
                           @if($banner->imageUrl()) poster="{{ $banner->imageUrl() }}" @endif></video>
                @elseif($banner?->imageUrl())
                    <img src="{{ $banner->imageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover" @if($i > 0) loading="lazy" @else fetchpriority="high" @endif decoding="async">
                @endif
            </div>
        @endforeach

        {{-- Light scrim: readable by default, fades on hover (hover-capable devices only) so the image shows fully --}}
        <div class="absolute inset-0 pointer-events-none bg-gradient-to-br from-brand-950/70 via-[#072f1b]/50 to-emerald-950/70 transition-opacity duration-500 group-hover/hero:opacity-20 group-focus-within/hero:opacity-100!"></div>

        {{-- Ambient Glow Orbs --}}
        <div class="hidden lg:block absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="hidden lg:block absolute top-1/2 -right-40 w-96 h-96 bg-accent-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-24 relative z-10">
            <div class="grid lg:grid-cols-12 gap-6 sm:gap-12 items-center">
                {{-- Left Editorial Headline: slides share one grid cell and cross-fade --}}
                <div class="lg:col-span-7 grid text-center lg:text-left">
                    @foreach($slides as $i => $banner)
                        <div data-hero-slide class="col-start-1 row-start-1 space-y-3 sm:space-y-6 transition-opacity duration-700 {{ $i === 0 ? '' : 'opacity-0 invisible' }}"
                             role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $slides->count() }}">
                            <div class="space-y-3 sm:space-y-6 transition-opacity duration-500 group-hover/hero:opacity-15 group-focus-within/hero:opacity-100!">
                                <div class="inline-flex items-center gap-2 px-3 sm:px-3.5 py-1 sm:py-1.5 rounded-full bg-emerald-950/60 border border-emerald-400/30 text-emerald-200 text-[11px] sm:text-xs font-semibold max-w-full">
                                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400 motion-safe:animate-pulse"></span>
                                    <span class="truncate">Single-Seller Farm Direct • {{ config('cymarket.pilot_zone_name') }}</span>
                                </div>

                                @if($i === 0)
                                    <h1 class="text-[1.75rem] sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.15] sm:leading-[1.1] text-balance break-words text-white [text-shadow:0_2px_12px_rgb(0_0_0/0.45)]">
                                        {{ $banner->title ?? 'Farm-Fresh Harvest, Delivered to Your Door.' }}
                                    </h1>
                                @else
                                    <h2 class="text-[1.75rem] sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.15] sm:leading-[1.1] text-balance break-words text-white [text-shadow:0_2px_12px_rgb(0_0_0/0.45)]">
                                        {{ $banner->title }}
                                    </h2>
                                @endif

                                <p class="text-sm sm:text-lg text-emerald-50 max-w-xl mx-auto lg:mx-0 font-normal leading-relaxed line-clamp-3 sm:line-clamp-none [text-shadow:0_1px_8px_rgb(0_0_0/0.5)]">
                                    {{ $banner->subtitle ?? 'Every fruit, vegetable, and grain on CY-Market is harvested directly from our own certified farms. No third-party middlemen — only genuine farm-fresh goodness.' }}
                                </p>
                            </div>

                            <div class="pt-1 sm:pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-2.5 sm:gap-4">
                                <a href="{{ $banner->link_url ?? route('products.index') }}"
                                   class="inline-flex items-center gap-2 sm:gap-2.5 bg-gradient-to-r from-emerald-500 to-brand-600 hover:from-emerald-600 hover:to-brand-700 text-white text-sm sm:text-base font-bold px-5 sm:px-7 py-2.5 sm:py-3.5 min-h-11 rounded-full shadow-lg shadow-emerald-600/30 hover:shadow-xl hover:shadow-emerald-600/40 hover:-translate-y-0.5 transition-all">
                                    <span>{{ $banner?->cta_label ?: 'Shop Fresh Produce' }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                                <a href="{{ route('orders.track-form') }}"
                                   class="inline-flex items-center gap-2 bg-emerald-950/60 hover:bg-emerald-950/75 border border-white/25 text-white text-sm sm:text-base font-semibold px-4 sm:px-6 py-2.5 sm:py-3.5 min-h-11 rounded-full transition-all">
                                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    <span>Track an Order</span>
                                </a>
                            </div>
                        </div>
                    @endforeach

                    @if($slides->count() > 1)
                        <div class="col-start-1 row-start-2 mt-4 sm:mt-6 flex items-center justify-center lg:justify-start gap-2" role="group" aria-label="Choose slide">
                            @foreach($slides as $i => $banner)
                                <button type="button" data-hero-dot="{{ $i }}" aria-label="Show slide {{ $i + 1 }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                        class="h-2.5 w-2.5 rounded-full bg-white/40 aria-[current=true]:bg-white aria-[current=true]:w-6 transition-all focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"></button>
                            @endforeach
                        </div>
                    @endif
                </div>


                {{-- Right 4 Glass Feature Tiles --}}
                <div class="lg:col-span-5 grid grid-cols-2 gap-2 sm:gap-3.5">
                    <div class="lg:backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-3 sm:p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-base sm:text-2xl font-black text-white leading-tight">100%</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Farm Grown</p>
                        <p class="hidden sm:block text-[11px] text-emerald-100/70 mt-1">Directly from our own fields, zero middlemen</p>
                    </div>

                    <div class="lg:backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-3 sm:p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-amber-500/20 flex items-center justify-center text-accent-400 mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="text-base sm:text-2xl font-black text-white leading-tight">Pay by Transfer</p>
                        <p class="text-xs font-bold text-amber-200 mt-0.5">Secure Checkout</p>
                        <p class="hidden sm:block text-[11px] text-emerald-100/70 mt-1">Pay our account directly, we confirm on our end</p>
                    </div>

                    <div class="lg:backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-3 sm:p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p class="text-base sm:text-2xl font-black text-white leading-tight">Live SMS</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Status Updates</p>
                        <p class="hidden sm:block text-[11px] text-emerald-100/70 mt-1">Automatic alert at dispatch and delivery</p>
                    </div>

                    <div class="lg:backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-3 sm:p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <p class="text-base sm:text-2xl font-black text-white leading-tight">Fast Delivery</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Pilot Guaranteed</p>
                        <p class="hidden sm:block text-[11px] text-emerald-100/70 mt-1">Cold-packed and dispatched promptly</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Category Spotlight Carousel/Grid --}}
    @if($featuredCategories->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-12">
            <div class="flex items-center justify-between gap-3 mb-4 sm:mb-6">
                <div>
                    <h2 class="text-lg sm:text-2xl font-black text-gray-900 tracking-tight">Explore by Category</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Browse harvest collections curated fresh daily</p>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 shrink-0 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors py-2">
                    <span>View all</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 sm:gap-4">
                @foreach($featuredCategories as $category)
                    <a href="{{ route('categories.show', $category) }}"
                       class="group relative block aspect-[4/3] sm:aspect-square overflow-hidden rounded-2xl sm:rounded-[26px] bg-gradient-to-br from-brand-600 to-brand-800 shadow-xs hover:shadow-lg hover:shadow-brand-950/20 hover:-translate-y-1 liquid-tap transition-all duration-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                        @if($category->image)
                            <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" width="400" height="400" loading="lazy" decoding="async"
                                 class="absolute inset-0 h-full w-full object-cover group-hover:scale-110 transition-transform duration-500">
                        @else
                            <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center pb-6 text-5xl font-black text-white/25">{{ Str::substr($category->name, 0, 1) }}</span>
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-transparent" aria-hidden="true"></div>
                        <div class="absolute inset-x-0 bottom-0 p-2.5 sm:p-3 text-left">
                            <p class="text-sm font-bold leading-tight text-white drop-shadow line-clamp-2">{{ $category->name }}</p>
                            <span class="mt-0.5 block text-[10px] font-medium text-white/80">Fresh harvest</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured Products Section --}}
    @if($featuredProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-8">
            <div class="flex items-end justify-between gap-3 mb-4 sm:mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Specially Curated</span>
                    <h2 class="text-xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Featured this Week</h2>
                </div>
                <a href="{{ route('products.index') }}" 
                   class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-brand-50 hover:bg-brand-100 text-brand-800 text-xs font-bold transition-all">
                    <span>View All Produce</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-6">
                @foreach($featuredProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust & Quality Assurance Glass Banner --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
        <div class="relative rounded-2xl sm:rounded-3xl bg-gradient-to-r from-brand-900 via-[#0c4028] to-brand-950 p-5 sm:p-12 text-white overflow-hidden shadow-xl shadow-brand-950/10">
            <div class="hidden sm:block absolute -right-20 -top-20 w-80 h-80 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid md:grid-cols-3 gap-5 sm:gap-8 items-center">
                <div class="md:col-span-2 space-y-3">
                    <span class="inline-flex items-center gap-1.5 text-accent-400 font-bold text-xs uppercase tracking-wider">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        100% Genuine Farm Harvest Guarantee
                    </span>
                    <h3 class="text-lg sm:text-3xl font-black tracking-tight text-white">
                        Fresh from the soil. Never stored in bulk depots.
                    </h3>
                    <p class="text-xs sm:text-sm text-emerald-100/80 max-w-xl leading-relaxed">
                        We harvest on-demand to guarantee pristine nutritional quality and peak taste. If any item arrives subpar, we issue an instant replacement or refund with zero hassle.
                    </p>
                </div>
                <div class="flex justify-start md:justify-end">
                    <a href="{{ route('products.index') }}" 
                       class="inline-flex items-center justify-center gap-2 w-full sm:w-auto min-h-11 bg-accent-500 hover:bg-accent-600 text-brand-950 font-extrabold px-6 py-3 sm:py-3.5 rounded-full shadow-lg shadow-accent-500/20 hover:scale-105 active:scale-95 transition-all text-sm">
                        <span>Browse Today's Harvest</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Trending / Best Selling Products --}}
    @if($trendingProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-8">
            <div class="flex items-end justify-between gap-3 mb-4 sm:mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Most Popular</span>
                    <h2 class="text-xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Trending Now</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'best_selling']) }}" 
                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    <span>View all trending</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-6">
                @foreach($trendingProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Newest Arrivals Section --}}
    @if($newestProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-8 pb-10 sm:pb-16">
            <div class="flex items-end justify-between gap-3 mb-4 sm:mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Fresh Harvest</span>
                    <h2 class="text-xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Just Picked & Added</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" 
                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    <span>View all new items</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2.5 sm:gap-6">
                @foreach($newestProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

@endsection

@push('scripts')
    <script>
        (function () {
            var hero = document.getElementById('hero');
            if (!hero) return;
            var slides = hero.querySelectorAll('[data-hero-slide]');
            var bgs = hero.querySelectorAll('[data-hero-bg]');
            var dots = hero.querySelectorAll('[data-hero-dot]');

            // Hero video: never autoplay on phones, data-saver or reduced-motion; the poster image shows instead.
            var conn = navigator.connection || {};
            var canPlayVideo = window.matchMedia('(min-width: 768px)').matches
                && !conn.saveData
                && !/(^|-)2g$/.test(conn.effectiveType || '')
                && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (!canPlayVideo) {
                hero.querySelectorAll('[data-hero-video]').forEach(function (v) { v.removeAttribute('autoplay'); v.pause(); });
            } else {
                var first = hero.querySelector('[data-hero-video]');
                if (first && first.closest('[data-hero-bg]') === bgs[0]) { first.preload = 'auto'; first.play().catch(function () {}); }
            }
            if (slides.length < 2) return;
            var current = 0, timer = null;

            function show(n) {
                current = (n + slides.length) % slides.length;
                slides.forEach(function (s, i) {
                    var on = i === current;
                    s.classList.toggle('opacity-0', !on);
                    s.classList.toggle('invisible', !on);
                    s.setAttribute('aria-hidden', on ? 'false' : 'true');
                });
                bgs.forEach(function (b, i) {
                    b.classList.toggle('opacity-0', i !== current);
                    var v = b.querySelector('video');
                    if (v) { if (i === current && canPlayVideo) { v.play().catch(function () {}); } else { v.pause(); } }
                });
                dots.forEach(function (d, i) { d.setAttribute('aria-current', i === current ? 'true' : 'false'); });
            }
            function stop() { clearInterval(timer); timer = null; }
            function start() {
                if (timer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                timer = setInterval(function () { show(current + 1); }, 6000);
            }
            dots.forEach(function (d, i) { d.addEventListener('click', function () { show(i); }); });
            hero.addEventListener('mouseenter', stop);
            hero.addEventListener('mouseleave', start);
            hero.addEventListener('focusin', stop);
            hero.addEventListener('focusout', start);
            document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
            show(0);
            start();
        })();
    </script>
@endpush
