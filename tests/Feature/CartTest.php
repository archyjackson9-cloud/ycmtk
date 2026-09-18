<?php

namespace Tests\Feature;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Guest & registered shopping cart (TOR §6.3 "Select products -> Add to
 * cart -> Review / update cart (respect minimum order quantity)").
 */
class CartTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    public function test_guest_can_add_a_product_to_the_cart(): void
    {
        $product = $this->makeProduct(['available_quantity' => 10, 'min_order_quantity' => 1]);

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_adding_a_product_twice_increments_quantity_instead_of_duplicating_the_line(): void
    {
        $product = $this->makeProduct(['available_quantity' => 10]);

        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 2]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);

        $this->assertSame(1, CartItem::where('product_id', $product->id)->count());
        $this->assertSame(5, CartItem::where('product_id', $product->id)->first()->quantity);
    }

    public function test_cannot_add_more_than_the_sellable_quantity(): void
    {
        $product = $this->makeProduct(['available_quantity' => 3, 'reserved_quantity' => 0]);

        $response = $this->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_customer_can_update_cart_item_quantity(): void
    {
        $product = $this->makeProduct(['available_quantity' => 10]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = CartItem::where('product_id', $product->id)->firstOrFail();

        $response = $this->patch(route('cart.update', $item), ['quantity' => 4]);

        $response->assertRedirect();
        $this->assertSame(4, $item->fresh()->quantity);
    }

    public function test_customer_can_remove_a_cart_item(): void
    {
        $product = $this->makeProduct(['available_quantity' => 10]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = CartItem::where('product_id', $product->id)->firstOrFail();

        $response = $this->delete(route('cart.destroy', $item));

        $response->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_updating_below_minimum_order_quantity_is_rejected(): void
    {
        $product = $this->makeProduct(['available_quantity' => 10, 'min_order_quantity' => 3]);
        $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 3]);
        $item = CartItem::where('product_id', $product->id)->firstOrFail();

        $response = $this->patch(route('cart.update', $item), ['quantity' => 1]);

        $response->assertSessionHas('error');
        $this->assertSame(3, $item->fresh()->quantity);
    }
}
