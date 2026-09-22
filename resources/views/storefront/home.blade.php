@extends('layouts.storefront')

@section('title', 'CY-Market — Farm-Fresh Produce, Direct from our Harvest')

@section('content')

    {{-- Hero Section - editable via Admin > Marketing > Banners --}}
    @php($banner = $heroBanners->first())
    <section class="relative bg-gradient-to-br from-brand-950 via-[#072f1b] to-emerald-950 text-white overflow-hidden">
        {{-- Background Media (image or video), sitting behind everything else --}}
        @if($banner?->hasVideo())
            <video src="{{ $banner->videoUrl() }}" class="absolute inset-0 w-full h-full object-cover" autoplay muted loop playsinline
                   @if($banner->imageUrl()) poster="{{ $banner->imageUrl() }}" @endif>
            </video>
        @elseif($banner?->imageUrl())
            <img src="{{ $banner->imageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover" aria-hidden="true">
        @endif

        {{-- Dark scrim so text stays legible over any background media --}}
        <div class="absolute inset-0 bg-gradient-to-br from-brand-950/90 via-[#072f1b]/85 to-emerald-950/90"></div>

        {{-- Ambient Glow Orbs --}}
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/2 -right-40 w-96 h-96 bg-accent-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-24 relative z-10">
            <div class="grid lg:grid-cols-12 gap-12 items-center">
                {{-- Left Editorial Headline --}}
                <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full backdrop-blur-md bg-emerald-500/15 border border-emerald-400/30 text-emerald-200 text-xs font-semibold">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Single-Seller Farm Direct • {{ config('cymarket.pilot_zone_name') }}</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight leading-[1.1] text-white">
                        {{ $banner->title ?? 'Farm-Fresh Harvest, Delivered to Your Door.' }}
                    </h1>

                    <p class="text-base sm:text-lg text-emerald-100/80 max-w-xl mx-auto lg:mx-0 font-normal leading-relaxed">
                        {{ $banner->subtitle ?? 'Every fruit, vegetable, and grain on CY-Market is harvested directly from our own certified farms. No third-party middlemen — only genuine farm-fresh goodness.' }}
                    </p>

                    <div class="pt-2 flex flex-wrap items-center justify-center lg:justify-start gap-4">
                        <a href="{{ $banner->link_url ?? route('products.index') }}"
                           class="inline-flex items-center gap-2.5 bg-gradient-to-r from-emerald-500 to-brand-600 hover:from-emerald-600 hover:to-brand-700 text-white font-bold px-7 py-3.5 rounded-full shadow-lg shadow-emerald-600/30 hover:shadow-xl hover:shadow-emerald-600/40 hover:-translate-y-0.5 transition-all">
                            <span>{{ $banner->cta_label ?? 'Shop Fresh Produce' }}</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                        <a href="{{ route('orders.track-form') }}"
                           class="inline-flex items-center gap-2 backdrop-blur-md bg-white/10 hover:bg-white/15 border border-white/20 text-white font-semibold px-6 py-3.5 rounded-full transition-all">
                            <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <span>Track an Order</span>
                        </a>
                    </div>
                </div>

                {{-- Right 4 Glass Feature Tiles --}}
                <div class="lg:col-span-5 grid grid-cols-2 gap-3.5">
                    <div class="backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-2xl font-black text-white">100%</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Farm Grown</p>
                        <p class="text-[11px] text-emerald-100/70 mt-1">Directly from our own fields, zero middlemen</p>
                    </div>

                    <div class="backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-10 w-10 rounded-xl bg-amber-500/20 flex items-center justify-center text-accent-400 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="text-2xl font-black text-white">Hubtel MoMo</p>
                        <p class="text-xs font-bold text-amber-200 mt-0.5">Instant Checkout</p>
                        <p class="text-[11px] text-emerald-100/70 mt-1">Protected Mobile Money & Card payment</p>
                    </div>

                    <div class="backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p class="text-2xl font-black text-white">Live SMS</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Status Updates</p>
                        <p class="text-[11px] text-emerald-100/70 mt-1">Automatic alert at dispatch and delivery</p>
                    </div>

                    <div class="backdrop-blur-xl bg-white/10 hover:bg-white/15 border border-white/20 rounded-2xl p-5 transition-all duration-300 shadow-lg shadow-black/10 group">
                        <div class="h-10 w-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 mb-3 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <p class="text-2xl font-black text-white">Fast Delivery</p>
                        <p class="text-xs font-bold text-emerald-200 mt-0.5">Pilot Guaranteed</p>
                        <p class="text-[11px] text-emerald-100/70 mt-1">Cold-packed and dispatched promptly</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Category Spotlight Carousel/Grid --}}
    @if($featuredCategories->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">Explore by Category</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Browse harvest collections curated fresh daily</p>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    <span>View all categories</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 sm:gap-4">
                @foreach($featuredCategories as $category)
                    <a href="{{ route('categories.show', $category) }}"
                       class="group flex flex-col items-center liquid-glass rounded-[26px] p-4 hover:shadow-lg hover:shadow-brand-950/10 hover:-translate-y-1 liquid-tap transition-all duration-300 text-center">
                        <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-brand-50 to-brand-100/80 group-hover:from-brand-100 group-hover:to-brand-200 overflow-hidden flex items-center justify-center border border-brand-200/50 shadow-2xs group-hover:scale-105 transition-transform duration-300">
                            @if($category->image)
                                <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="h-full w-full object-cover">
                            @else
                                <span class="text-brand-800 font-extrabold text-xl">{{ Str::substr($category->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <p class="mt-3 text-xs font-bold text-gray-800 group-hover:text-brand-700 transition-colors line-clamp-1">{{ $category->name }}</p>
                        <span class="text-[10px] text-gray-400 mt-0.5 font-medium">Fresh harvest</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured Products Section --}}
    @if($featuredProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Specially Curated</span>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Featured this Week</h2>
                </div>
                <a href="{{ route('products.index') }}" 
                   class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-brand-50 hover:bg-brand-100 text-brand-800 text-xs font-bold transition-all">
                    <span>View All Produce</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($featuredProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Trust & Quality Assurance Glass Banner --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
        <div class="relative rounded-3xl bg-gradient-to-r from-brand-900 via-[#0c4028] to-brand-950 p-8 sm:p-12 text-white overflow-hidden shadow-xl shadow-brand-950/10">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid md:grid-cols-3 gap-8 items-center">
                <div class="md:col-span-2 space-y-3">
                    <span class="inline-flex items-center gap-1.5 text-accent-400 font-bold text-xs uppercase tracking-wider">
                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        100% Genuine Farm Harvest Guarantee
                    </span>
                    <h3 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                        Fresh from the soil. Never stored in bulk depots.
                    </h3>
                    <p class="text-sm text-emerald-100/80 max-w-xl leading-relaxed">
                        We harvest on-demand to guarantee pristine nutritional quality and peak taste. If any item arrives subpar, we issue an instant replacement or refund with zero hassle.
                    </p>
                </div>
                <div class="flex justify-start md:justify-end">
                    <a href="{{ route('products.index') }}" 
                       class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-brand-950 font-extrabold px-6 py-3.5 rounded-full shadow-lg shadow-accent-500/20 hover:scale-105 active:scale-95 transition-all text-sm">
                        <span>Browse Today's Harvest</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Trending / Best Selling Products --}}
    @if($trendingProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Most Popular</span>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Trending Now</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'best_selling']) }}" 
                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    <span>View all trending</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($trendingProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Newest Arrivals Section --}}
    @if($newestProducts->isNotEmpty())
        <section class="max-w-7xl mx-auto px-4 sm:px-6 py-8 pb-16">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Fresh Harvest</span>
                    <h2 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight mt-0.5">Just Picked & Added</h2>
                </div>
                <a href="{{ route('products.index', ['sort' => 'newest']) }}" 
                   class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    <span>View all new items</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($newestProducts as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

@endsection
