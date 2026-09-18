@extends('layouts.storefront')

@section('title', $activeCategory?->name ?? ($filters['q'] ?? 'All Farm Produce'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">

    {{-- Breadcrumb Navigation --}}
    <nav class="flex items-center gap-2 text-xs font-semibold text-gray-400 mb-6">
        <a href="{{ route('home') }}" class="hover:text-brand-700 transition-colors flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>Home</span>
        </a>
        <span>/</span>
        @if($activeCategory)
            <a href="{{ route('products.index') }}" class="hover:text-brand-700 transition-colors">Shop</a>
            <span>/</span>
            <span class="text-brand-800 font-bold bg-brand-50 px-2 py-0.5 rounded-md">{{ $activeCategory->name }}</span>
        @else
            <span class="text-gray-900 font-bold">All Produce</span>
        @endif
    </nav>

    {{-- Page Title & Meta --}}
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 pb-6 border-b border-gray-200/70">
        <div>
            <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">
                @if($activeCategory) 
                    {{ $activeCategory->name }}
                @elseif($filters['q'] ?? null) 
                    Search: <span class="text-brand-700">"{{ $filters['q'] }}"</span>
                @else 
                    Fresh Farm Produce
                @endif
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                @if($activeCategory?->description)
                    {{ $activeCategory->description }}
                @else
                    Sustainably farmed, harvested daily, and delivered directly to your doorstep.
                @endif
            </p>
        </div>
        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full liquid-glass text-xs font-bold text-gray-700 shadow-2xs">
            <span class="h-2 w-2 rounded-full bg-brand-500"></span>
            <span>{{ $products->total() }} items found</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        {{-- Filters Sidebar (Sticky Glass Card) --}}
        <aside class="lg:col-span-1">
            <div class="liquid-glass-strong rounded-[28px] p-6 shadow-xs lg:sticky lg:top-28 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto space-y-6">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filter Produce
                    </h3>
                    @if(request()->hasAny(['category', 'q', 'min_price', 'max_price', 'in_stock_only']))
                        <a href="{{ route('products.index') }}" class="text-[11px] font-bold text-rose-600 hover:underline">Reset</a>
                    @endif
                </div>

                <form method="GET" action="{{ route('products.index') }}" class="space-y-6">
                    @if($activeCategory)
                        <input type="hidden" name="category" value="{{ $activeCategory->slug }}">
                    @endif
                    @if($filters['q'] ?? null)
                        <input type="hidden" name="q" value="{{ $filters['q'] }}">
                    @endif

                    {{-- Categories Filter --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 mb-2 uppercase tracking-wider">Categories</h4>
                        <div class="space-y-1 max-h-56 overflow-y-auto pr-1 text-xs">
                            <a href="{{ route('products.index', array_filter(['q' => $filters['q'] ?? null])) }}" 
                               class="flex items-center justify-between px-3 py-2 rounded-xl transition-all {{ !$activeCategory ? 'bg-brand-600 text-white font-bold shadow-xs' : 'text-gray-600 hover:bg-gray-100' }}">
                                <span>All Categories</span>
                            </a>
                            @foreach($categories as $category)
                                <a href="{{ route('categories.show', $category) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-xl transition-all {{ $activeCategory?->id === $category->id ? 'bg-brand-50 text-brand-800 font-bold border border-brand-200' : 'text-gray-600 hover:bg-brand-50/60 hover:text-brand-700' }}">
                                    <span>{{ $category->name }}</span>
                                    @if($activeCategory?->id === $category->id)
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-600"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Price Filter --}}
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 mb-2 uppercase tracking-wider">Price (GHS)</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">₵</span>
                                <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Min"
                                       class="w-full rounded-xl liquid-input text-xs pl-6 pr-2 py-2 focus:outline-none">
                            </div>
                            <div class="relative">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">₵</span>
                                <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Max"
                                       class="w-full rounded-xl liquid-input text-xs pl-6 pr-2 py-2 focus:outline-none">
                            </div>
                        </div>
                    </div>

                    {{-- Stock Filter --}}
                    <div class="pt-2 border-t border-gray-100">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" name="in_stock_only" id="in_stock_only" value="1" 
                                   {{ ($filters['in_stock_only'] ?? false) ? 'checked' : '' }} 
                                   class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-xs font-semibold text-gray-700">In Stock Only</span>
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full bg-brand-600 hover:bg-brand-700 active:scale-[0.99] text-white text-xs font-bold rounded-full py-2.5 shadow-md shadow-brand-600/20 transition-all">
                        Apply Filters
                    </button>
                </form>
            </div>
        </aside>

        {{-- Results Area --}}
        <div class="lg:col-span-3 space-y-6">

            {{-- Top Sort & Active Filter Toolbar --}}
            <div class="flex items-center justify-between liquid-glass rounded-[22px] p-3.5 shadow-2xs">
                <span class="text-xs text-gray-500 font-medium">
                    Showing <strong class="text-gray-900">{{ $products->count() }}</strong> of <strong class="text-gray-900">{{ $products->total() }}</strong> items
                </span>

                {{-- Sort Dropdown --}}
                <form method="GET" class="flex items-center gap-2 text-xs">
                    @foreach(request()->except('sort', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <label class="text-gray-500 font-medium hidden sm:inline">Sort by:</label>
                    <div class="relative">
                        <select name="sort" onchange="this.form.submit()"
                                class="appearance-none rounded-xl liquid-input text-xs font-bold text-gray-800 py-1.5 pl-3 pr-8 focus:outline-none cursor-pointer">
                            <option value="relevance" {{ ($filters['sort'] ?? '') === 'relevance' ? 'selected' : '' }}>Featured / Relevance</option>
                            <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                            <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                            <option value="newest" {{ ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' }}>Newest Harvest</option>
                            <option value="best_selling" {{ ($filters['sort'] ?? '') === 'best_selling' ? 'selected' : '' }}>Best Selling</option>
                            <option value="rating" {{ ($filters['sort'] ?? '') === 'rating' ? 'selected' : '' }}>Top Customer Rated</option>
                        </select>
                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Products Grid --}}
            @if($products->isEmpty())
                <div class="liquid-glass rounded-[32px] p-12 text-center space-y-4 shadow-xs">
                    <div class="h-16 w-16 mx-auto rounded-full bg-brand-50 text-brand-600 flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-gray-900">No produce matching your criteria</h3>
                        <p class="text-xs text-gray-500 max-w-sm mx-auto">Try adjusting your price range, searching for another keyword, or clearing the active filters.</p>
                    </div>
                    <div>
                        <a href="{{ route('products.index') }}" 
                           class="inline-flex items-center gap-2 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-5 py-2.5 rounded-full shadow-xs transition-all">
                            <span>Browse All Farm Harvest</span>
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    @foreach($products as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="pt-8">
                    {{ $products->links() }}
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
