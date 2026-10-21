<nav aria-label="Product categories" class="sm:border-t border-white/50">
    <div class="px-4 sm:px-6">
        <ul class="flex items-center gap-2 overflow-x-auto text-xs font-semibold py-2 sm:py-2.5 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
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
