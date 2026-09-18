<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Catalogue listing/search (TOR §6.2 Search, Filtering & Discovery) and
 * product detail page (TOR §8.2).
 */
class ProductController extends Controller
{
    /**
     * GET /shop - also used by CategoryController@show, which forwards
     * here with `category` pre-filled from the route.
     */
    public function index(Request $request)
    {
        $query = Product::query()->active()->with(['images', 'category']);

        if ($categorySlug = $request->query('category')) {
            $category = Category::where('slug', $categorySlug)->firstOrFail();
            $categoryIds = $category->children()->pluck('id')->push($category->id);
            $query->whereIn('category_id', $categoryIds);
        } else {
            $category = null;
        }

        $query->search($request->query('q'));

        if ($request->filled('min_price')) {
            $query->where('selling_price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('selling_price', '<=', (float) $request->query('max_price'));
        }
        if ($request->boolean('in_stock_only')) {
            $query->inStock();
        }

        $sort = $request->query('sort', 'relevance');
        match ($sort) {
            'price_asc' => $query->orderBy('selling_price'),
            'price_desc' => $query->orderByDesc('selling_price'),
            'newest' => $query->latest(),
            'best_selling' => $query->orderByDesc('sold_count'),
            'rating' => $query->orderByDesc('average_rating'),
            default => $query->orderByDesc('is_featured')->orderByDesc('sold_count'),
        };

        // Seasonal availability (TOR §6.1) flags rather than hides - out of
        // season items stay discoverable but are clearly labelled in the
        // view via $product->is_in_season.
        $products = $query->paginate(20)->withQueryString();

        $categories = Category::active()->roots()->orderBy('sort_order')->get();

        return view('storefront.products.index', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $category,
            'filters' => $request->only(['q', 'min_price', 'max_price', 'sort', 'in_stock_only']),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->increment('views_count');
        $product->load(['images', 'category', 'reviews.user']);

        $related = Product::query()
            ->active()->inStock()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with('images')
            ->limit(8)
            ->get();

        return view('storefront.products.show', compact('product', 'related'));
    }
}
