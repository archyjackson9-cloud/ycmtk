<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(protected CartService $carts) {}

    public function index(Request $request)
    {
        $cart = $this->carts->currentCart($request->user(), $request->session()->getId());
        $cart->load('items.product.images');

        $issues = $this->carts->validateForCheckout($cart);

        return view('storefront.cart.index', [
            'cart' => $cart->fresh('items.product.images'),
            'issues' => $issues,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $cart = $this->carts->currentCart($request->user(), $request->session()->getId());

        try {
            $this->carts->addItem($cart, $product, (int) $data['quantity']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$product->name} added to your cart.");
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        try {
            $this->carts->updateQuantity($item, (int) $data['quantity']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $this->carts->removeItem($item);

        return back()->with('success', 'Item removed from your cart.');
    }

    protected function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $this->carts->currentCart($request->user(), $request->session()->getId());

        abort_unless($item->cart_id === $cart->id, 403);
    }
}
