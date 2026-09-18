@extends('layouts.storefront')

@section('title', $product->name . ' — Farm-Fresh Direct')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    {{-- Breadcrumbs --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-8">
        <a href="{{ route('home') }}" class="hover:text-brand-700 transition-colors">Home</a>
        <span>/</span>
        @if($product->category)
            <a href="{{ route('categories.show', $product->category) }}" class="hover:text-brand-700 transition-colors">{{ $product->category->name }}</a>
            <span>/</span>
        @endif
        <span class="text-gray-900 font-bold truncate max-w-xs sm:max-w-md">{{ $product->name }}</span>
    </nav>

    <div class="grid lg:grid-cols-12 gap-10 lg:gap-14">
        {{-- Left: Image Gallery with Glass Badges --}}
        <div class="lg:col-span-6 space-y-4">
            @php($primary = $product->images->first())
            <div class="aspect-square bg-gradient-to-b from-gray-50 to-gray-100/80 rounded-3xl overflow-hidden border border-gray-200/80 relative shadow-sm flex items-center justify-center group">
                @if($primary)
                    <img id="main-image" src="{{ $primary->url() }}" alt="{{ $product->name }}" 
                         class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                @else
                    <div class="flex flex-col items-center gap-2 text-gray-300">
                        <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-xs font-semibold text-gray-400">Fresh Harvest</span>
                    </div>
                @endif

                {{-- Status Overlay Badges --}}
                <div class="absolute top-4 left-4 flex flex-col gap-2 z-10 pointer-events-none">
                    @if($product->is_out_of_stock)
                        <span class="inline-flex items-center gap-1.5 backdrop-blur-md bg-gray-900/85 text-white border border-white/20 text-xs font-bold px-3 py-1.5 rounded-full shadow-md">
                            <span class="h-2 w-2 rounded-full bg-rose-400"></span>
                            Out of stock
                        </span>
                    @elseif($product->is_low_stock)
                        <span class="inline-flex items-center gap-1.5 backdrop-blur-md bg-amber-500/90 text-white border border-amber-300/30 text-xs font-bold px-3 py-1.5 rounded-full shadow-md">
                            <span class="h-2 w-2 rounded-full bg-white animate-pulse"></span>
                            Only {{ $product->sellable_quantity }} left in harvest
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 backdrop-blur-md bg-brand-800/85 text-white border border-white/20 text-xs font-bold px-3 py-1.5 rounded-full shadow-md">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Farm-Fresh Harvest
                        </span>
                    @endif
                </div>

                @if(!$product->is_in_season)
                    <span class="absolute top-4 right-4 backdrop-blur-md bg-white/90 text-gray-700 border border-white/60 text-xs font-bold px-3 py-1.5 rounded-full shadow-md z-10 pointer-events-none">
                        Off-season Produce
                    </span>
                @endif
            </div>

            {{-- Thumbnail Selector --}}
            @if($product->images->count() > 1)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @foreach($product->images as $image)
                        <button type="button" 
                                onclick="document.getElementById('main-image').src = '{{ $image->url() }}'" 
                                class="h-20 w-20 rounded-2xl overflow-hidden border-2 border-transparent hover:border-brand-500 focus:border-brand-600 focus:outline-none transition-all shrink-0 bg-gray-50 shadow-2xs">
                            <img src="{{ $image->url() }}" alt="" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Right: Product Information & Purchase Form --}}
        <div class="lg:col-span-6 space-y-6">
            <div>
                @if($product->category)
                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-widest text-brand-600 bg-brand-50 border border-brand-200/50 px-3 py-1 rounded-full mb-3">
                        {{ $product->category->name }}
                    </span>
                @endif
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 tracking-tight leading-tight">
                    {{ $product->name }}
                </h1>

                {{-- Rating and Sold Meta --}}
                <div class="mt-3 flex items-center gap-4 text-xs font-semibold">
                    @if($product->ratings_count > 0)
                        <div class="flex items-center gap-1 text-amber-500 bg-amber-50 border border-amber-200/60 px-2.5 py-1 rounded-full">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span>{{ number_format($product->average_rating, 1) }}</span>
                            <span class="text-gray-500 font-normal">({{ $product->ratings_count }} reviews)</span>
                        </div>
                    @else
                        <span class="text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">No reviews yet</span>
                    @endif

                    <span class="text-gray-300">•</span>
                    <span class="text-gray-500">{{ $product->sold_count }} units delivered</span>
                </div>
            </div>

            {{-- Glass Price Card --}}
            <div class="glass-card rounded-3xl p-6 border border-brand-100 bg-gradient-to-br from-brand-50/50 via-white to-brand-50/30 space-y-4">
                <div class="flex items-baseline justify-between">
                    <div>
                        <span class="text-3xl sm:text-4xl font-black text-brand-950 tracking-tight">
                            {{ config('cymarket.currency_symbol') }}{{ number_format($product->selling_price, 2) }}
                        </span>
                        <span class="text-sm font-semibold text-gray-400 ml-1">/ {{ $product->unit_of_measurement }}</span>
                    </div>

                    @if(!$product->is_out_of_stock)
                        <div class="flex items-center gap-1.5 text-xs font-bold text-brand-700 bg-brand-100/70 border border-brand-200/60 px-3 py-1 rounded-full">
                            <span class="h-2 w-2 rounded-full bg-brand-600 animate-pulse"></span>
                            <span>{{ $product->sellable_quantity }} {{ $product->unit_of_measurement }} available</span>
                        </div>
                    @endif
                </div>

                @if($product->short_description)
                    <p class="text-xs sm:text-sm text-gray-600 leading-relaxed pt-2 border-t border-brand-100/60">
                        {{ $product->short_description }}
                    </p>
                @endif

                {{-- Add to Cart Form --}}
                <form action="{{ route('cart.store') }}" method="POST" class="pt-2 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    {{-- Quantity Selector --}}
                    <div class="flex items-center liquid-glass rounded-full shadow-2xs p-1">
                        <button type="button" 
                                onclick="const inp = document.getElementById('qty-input'); if(inp.value > {{ $product->min_order_quantity }}) inp.value--;"
                                class="w-8 h-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 font-bold transition-colors"
                                {{ $product->is_out_of_stock ? 'disabled' : '' }}>-</button>
                        <input type="number" id="qty-input" name="quantity" 
                               value="{{ $product->min_order_quantity }}" 
                               min="{{ $product->min_order_quantity }}" 
                               max="{{ $product->sellable_quantity }}"
                               class="w-12 text-center text-sm font-bold text-gray-900 border-0 p-0 focus:ring-0 focus:outline-none"
                               {{ $product->is_out_of_stock ? 'disabled' : '' }}>
                        <button type="button" 
                                onclick="const inp = document.getElementById('qty-input'); if(inp.value < {{ $product->sellable_quantity }}) inp.value++;"
                                class="w-8 h-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 font-bold transition-colors"
                                {{ $product->is_out_of_stock ? 'disabled' : '' }}>+</button>
                    </div>

                    {{-- Submit CTA --}}
                    <button type="submit" 
                            class="flex-1 inline-flex items-center justify-center gap-2 bg-gradient-to-r from-brand-600 to-brand-700 hover:from-brand-700 hover:to-brand-800 disabled:from-gray-300 disabled:to-gray-400 text-white font-extrabold text-sm py-3.5 px-6 rounded-full shadow-lg shadow-brand-600/25 hover:shadow-xl hover:shadow-brand-600/35 hover:-translate-y-0.5 active:scale-[0.98] transition-all"
                            {{ $product->is_out_of_stock ? 'disabled' : '' }}>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                        <span>{{ $product->is_out_of_stock ? 'Sold Out' : 'Add to Fresh Cart' }}</span>
                    </button>
                </form>

                @if($product->min_order_quantity > 1)
                    <p class="text-[11px] text-gray-500 text-center font-medium">Minimum order quantity: {{ $product->min_order_quantity }} {{ $product->unit_of_measurement }}</p>
                @endif
            </div>

            {{-- Farm Traceability Specs Grid --}}
            <div class="liquid-glass rounded-[28px] p-6 shadow-2xs space-y-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-gray-900 flex items-center gap-2">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Farm-to-Table Traceability
                </h3>
                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div class="p-3 rounded-2xl liquid-input">
                        <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider">Harvest Location</span>
                        <span class="font-bold text-gray-800 mt-0.5 block">{{ $product->production_location ?? 'CY-Market Farms, Tarkwa' }}</span>
                    </div>
                    <div class="p-3 rounded-2xl liquid-input">
                        <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider">Packaging Standard</span>
                        <span class="font-bold text-gray-800 mt-0.5 block">{{ $product->packaging_type ?? 'Eco-Safe Ventilated Crate' }}</span>
                    </div>
                    <div class="p-3 rounded-2xl liquid-input">
                        <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider">Portion / Weight</span>
                        <span class="font-bold text-gray-800 mt-0.5 block">{{ $product->weight ?? 'Standard Single Unit' }}</span>
                    </div>
                    <div class="p-3 rounded-2xl liquid-input">
                        <span class="text-gray-400 block text-[10px] font-bold uppercase tracking-wider">Product SKU</span>
                        <span class="font-mono font-bold text-gray-800 mt-0.5 block">{{ $product->sku }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Product Description Full --}}
    @if($product->description)
        <div class="mt-16 liquid-glass-strong rounded-[32px] p-8 sm:p-10 shadow-xs max-w-4xl">
            <h2 class="text-xl font-black text-gray-900 tracking-tight mb-4 flex items-center gap-2">
                <span>Produce Details & Preparation</span>
            </h2>
            <div class="prose prose-sm text-gray-600 leading-relaxed whitespace-pre-line">
                {{ $product->description }}
            </div>
        </div>
    @endif

    {{-- Customer Reviews --}}
    @if($product->reviews->isNotEmpty())
        <div class="mt-10 liquid-glass-strong rounded-[32px] p-8 sm:p-10 shadow-xs max-w-4xl">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-100">
                <h2 class="text-xl font-black text-gray-900 tracking-tight">Verified Harvest Reviews</h2>
                <div class="flex items-center gap-1 text-amber-500 font-bold text-sm">
                    <span>★ {{ number_format($product->average_rating, 1) }}</span>
                    <span class="text-gray-400 font-normal">({{ $product->reviews->count() }} ratings)</span>
                </div>
            </div>

            <div class="space-y-4 divide-y divide-gray-100">
                @foreach($product->reviews as $review)
                    <div class="pt-4 first:pt-0">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-amber-500 text-xs font-bold">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                                <span class="font-bold text-xs text-gray-900">{{ $review->user->name }}</span>
                                @if($review->is_verified_purchase)
                                    <span class="inline-flex items-center gap-0.5 text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 px-2 py-0.5 rounded-full">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Verified Harvest Buyer
                                    </span>
                                @endif
                            </div>
                            <span class="text-[11px] text-gray-400">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        @if($review->title)
                            <p class="font-bold text-xs text-gray-800 mt-1.5">{{ $review->title }}</p>
                        @endif
                        @if($review->body)
                            <p class="text-xs text-gray-600 mt-1 leading-relaxed">{{ $review->body }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Related Products --}}
    @if($related->isNotEmpty())
        <div class="mt-16 pt-10 border-t border-gray-200/80">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <span class="text-xs font-extrabold uppercase tracking-widest text-brand-600">Fresh Recommendations</span>
                    <h2 class="text-2xl font-black text-gray-900 tracking-tight mt-0.5">Complementary Produce</h2>
                </div>
                <a href="{{ route('products.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-800 transition-colors">
                    Browse all produce →
                </a>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                @foreach($related as $item)
                    @include('partials.product-card', ['product' => $item])
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
