@php($image = $product->images->first())
<div class="group relative flex flex-col liquid-glass rounded-[26px] shadow-xs hover:shadow-xl hover:shadow-brand-950/10 hover:-translate-y-1 liquid-tap transition-all duration-300 overflow-hidden">

    <a href="{{ route('products.show', $product) }}" class="contents">
        {{-- Product Image Container --}}
        <div class="aspect-square bg-gradient-to-b from-gray-50 to-gray-100/70 relative overflow-hidden flex items-center justify-center">
            @if($image)
                <img src="{{ $image->url() }}" alt="{{ $product->name }}"
                     class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
                     loading="lazy">
            @else
                <div class="h-full w-full flex flex-col items-center justify-center text-gray-300 gap-1 bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-[10px] text-gray-400 font-medium">CY-Market Fresh</span>
                </div>
            @endif

            {{-- Glassy Status Badges --}}
            <div class="absolute top-2.5 left-2.5 flex flex-col gap-1.5 z-10 pointer-events-none">
                @if($product->is_out_of_stock)
                    <span class="inline-flex items-center gap-1 backdrop-blur-md bg-gray-900/85 text-white border border-white/20 text-[10px] font-bold px-2.5 py-1 rounded-full shadow-xs">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
                        Out of stock
                    </span>
                @elseif($product->is_low_stock)
                    <span class="inline-flex items-center gap-1 backdrop-blur-md bg-amber-500/90 text-white border border-amber-300/30 text-[10px] font-bold px-2.5 py-1 rounded-full shadow-xs">
                        <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                        Low stock
                    </span>
                @endif
            </div>

            @if(!$product->is_in_season)
                <span class="absolute top-2.5 right-2.5 backdrop-blur-md bg-white/85 text-gray-600 border border-white/60 text-[10px] font-bold px-2.5 py-1 rounded-full shadow-xs z-10 pointer-events-none">
                    Off-season
                </span>
            @endif

            {{-- Subtle bottom gradient for image contrast --}}
            <div class="absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-black/5 to-transparent pointer-events-none"></div>
        </div>
    </a>

    {{-- Product Meta & Pricing --}}
    <div class="p-4 flex-1 flex flex-col justify-between gap-2.5">
        <a href="{{ route('products.show', $product) }}" class="contents">
            <div>
                @if($product->category)
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-brand-600 mb-1">
                        {{ $product->category->name }}
                    </p>
                @endif
                <h3 class="text-sm font-bold text-gray-900 group-hover:text-brand-700 transition-colors line-clamp-2 leading-snug">
                    {{ $product->name }}
                </h3>

                {{-- Rating and feedback --}}
                <div class="mt-1.5 flex items-center gap-1.5 text-xs">
                    @if($product->ratings_count > 0)
                        <div class="inline-flex items-center gap-0.5 text-amber-500 font-semibold text-[11px]">
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <span>{{ number_format($product->average_rating, 1) }}</span>
                        </div>
                        <span class="text-gray-400 text-[11px]">({{ $product->ratings_count }})</span>
                    @else
                        <span class="text-[11px] text-gray-400">Farm Fresh</span>
                    @endif
                </div>
            </div>
        </a>

        {{-- Pricing Row + Add to Cart --}}
        <div class="pt-2.5 border-t border-white/60 flex items-baseline justify-between gap-2">
            <a href="{{ route('products.show', $product) }}" class="min-w-0">
                <span class="text-base font-black text-gray-900 tracking-tight">
                    {{ config('cymarket.currency_symbol') }}{{ number_format($product->selling_price, 2) }}
                </span>
                <span class="text-[11px] font-medium text-gray-400">/ {{ $product->unit_of_measurement }}</span>
            </a>

            <form action="{{ route('cart.store') }}" method="POST" class="shrink-0">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="{{ $product->min_order_quantity }}">
                <button type="submit"
                        title="{{ $product->is_out_of_stock ? 'Out of stock' : 'Add to cart' }}"
                        {{ $product->is_out_of_stock ? 'disabled' : '' }}
                        class="h-8 w-8 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center transition-all duration-300 shadow-2xs hover:bg-brand-600 hover:text-white hover:shadow-md hover:shadow-brand-600/25 active:scale-90 disabled:opacity-40 disabled:pointer-events-none">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                </button>
            </form>
        </div>
    </div>
</div>
