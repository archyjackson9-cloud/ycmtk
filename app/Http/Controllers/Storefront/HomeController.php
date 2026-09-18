<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

/**
 * Merchandised homepage (TOR §8.1: hero banner/carousel, category
 * mega-menu, flash-deal rail, trending/"just for you" rails, footer).
 * Flash sales are Phase 2; the hero falls back to a CY-Market-authored
 * banner set when no CMS banners have been added yet.
 */
class HomeController extends Controller
{
    public function index()
    {
        $heroBanners = Banner::live('homepage_hero')->get();

        $featuredCategories = Category::active()->roots()->orderBy('sort_order')->limit(8)->get();

        $featuredProducts = Product::query()->active()->inStock()->featured()->with('images')->limit(8)->get();

        $trendingProducts = Product::query()->active()->inStock()->with('images')
            ->orderByDesc('sold_count')->limit(8)->get();

        $newestProducts = Product::query()->active()->inStock()->with('images')
            ->latest()->limit(8)->get();

        return view('storefront.home', compact(
            'heroBanners', 'featuredCategories', 'featuredProducts', 'trendingProducts', 'newestProducts'
        ));
    }
}
