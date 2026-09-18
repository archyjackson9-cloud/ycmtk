<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Shopping Cart Service (TOR §5.2, §6.3). Supports both guest carts (keyed
 * by session id) and registered-customer carts, merged on login.
 */
class CartService
{
    public function currentCart(?User $user, string $sessionId): Cart
    {
        if ($user) {
            return Cart::firstOrCreate(
                ['user_id' => $user->id, 'status' => 'active'],
                ['expires_at' => now()->addHours(config('cymarket.abandoned_cart_hours'))]
            );
        }

        return Cart::firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null, 'status' => 'active'],
            ['expires_at' => now()->addHours(config('cymarket.abandoned_cart_hours'))]
        );
    }

    /**
     * TOR §6.3 "Select products -> Add to cart", respecting minimum order
     * quantity (TOR §6.3 "Review / update cart (respect minimum order
     * quantity)") and available stock.
     */
    public function addItem(Cart $cart, Product $product, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        return DB::transaction(function () use ($cart, $product, $quantity) {
            $item = CartItem::firstOrNew([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_variant_id' => null,
            ]);

            $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;
            $newQuantity = max($newQuantity, $product->min_order_quantity);

            if ($newQuantity > $product->sellable_quantity) {
                throw new InvalidArgumentException("Only {$product->sellable_quantity} {$product->unit_of_measurement}(s) of {$product->name} available.");
            }

            $item->quantity = $newQuantity;
            $item->unit_price = $product->selling_price;
            $item->save();

            $cart->touch();

            return $item;
        });
    }

    public function updateQuantity(CartItem $item, int $quantity): CartItem
    {
        $product = $item->product;

        if ($quantity < $product->min_order_quantity) {
            throw new InvalidArgumentException("Minimum order quantity for {$product->name} is {$product->min_order_quantity}.");
        }

        if ($quantity > $product->sellable_quantity) {
            throw new InvalidArgumentException("Only {$product->sellable_quantity} {$product->unit_of_measurement}(s) of {$product->name} available.");
        }

        $item->update(['quantity' => $quantity, 'unit_price' => $product->selling_price]);
        $item->cart->touch();

        return $item;
    }

    public function removeItem(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $cart->touch();
    }

    /**
     * On login, fold the guest session cart into the customer's own cart so
     * items added while browsing as a guest are not lost.
     */
    public function mergeGuestCartIntoUser(string $sessionId, User $user): void
    {
        $guestCart = Cart::where('session_id', $sessionId)->where('status', 'active')->first();

        if (! $guestCart || $guestCart->items->isEmpty()) {
            return;
        }

        $userCart = $this->currentCart($user, $sessionId);

        foreach ($guestCart->items as $guestItem) {
            try {
                $this->addItem($userCart, $guestItem->product, $guestItem->quantity);
            } catch (InvalidArgumentException) {
                // Skip lines that no longer fit (out of stock, etc.) rather
                // than blocking login.
            }
        }

        $guestCart->update(['status' => 'converted']);
    }

    /**
     * Re-validate every line before checkout (TOR §11 "Product becomes
     * unavailable while in cart -> re-validates stock and minimum
     * quantities; removes or adjusts unavailable items with clear
     * notification").
     *
     * @return array<int, string> human-readable issues; empty = cart is checkout-ready.
     */
    public function validateForCheckout(Cart $cart): array
    {
        $issues = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_active || ! $product->is_in_season) {
                $issues[] = "{$item->product?->name} is no longer available and was removed from your cart.";
                $item->delete();

                continue;
            }

            if ($item->quantity > $product->sellable_quantity) {
                if ($product->sellable_quantity <= 0) {
                    $issues[] = "{$product->name} just sold out and was removed from your cart.";
                    $item->delete();
                } else {
                    $issues[] = "Only {$product->sellable_quantity} {$product->unit_of_measurement}(s) of {$product->name} left - quantity adjusted.";
                    $item->update(['quantity' => $product->sellable_quantity]);
                }

                continue;
            }

            if ($item->quantity < $product->min_order_quantity) {
                $issues[] = "{$product->name} requires a minimum order quantity of {$product->min_order_quantity}.";
                $item->update(['quantity' => $product->min_order_quantity]);
            }

            // Keep the snapshot price current in case it changed since it
            // was added to the cart.
            if ((float) $item->unit_price !== (float) $product->selling_price) {
                $item->update(['unit_price' => $product->selling_price]);
            }
        }

        return $issues;
    }
}
