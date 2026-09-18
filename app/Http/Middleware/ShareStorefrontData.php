<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Services\CartService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the mega-menu category tree and current cart item count with every
 * storefront view (TOR §8.1 "category mega-menu", persistent/sticky cart
 * summary).
 */
class ShareStorefrontData
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('megaMenuCategories', Category::query()
            ->active()
            ->roots()
            ->with(['children' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get());

        $cart = app(CartService::class)->currentCart($request->user(), $request->session()->getId());
        View::share('cartItemCount', $cart->items()->sum('quantity'));

        return $next($request);
    }
}
