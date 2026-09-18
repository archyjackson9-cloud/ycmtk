<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cart & Checkout Service (TOR §5.2, §6.3). Orchestrates the pilot-baseline
 * checkout flow: validate cart -> compute delivery fee -> create order ->
 * soft-reserve stock -> hand off to the Payment Service.
 */
class CheckoutService
{
    public function __construct(
        protected CartService $carts,
        protected StockService $stock,
        protected PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @param  array{recipient_name:string, phone:string, delivery_zone_id:?int, address_line:string, landmark:?string, latitude:?float, longitude:?float, notes:?string, coupon_code:?string, guest_name:?string, guest_email:?string}  $delivery
     * @return array{order: Order, redirect_url: string}
     *
     * @throws CheckoutException
     */
    public function checkout(Cart $cart, array $delivery, ?User $user): array
    {
        $issues = $this->carts->validateForCheckout($cart->fresh('items.product'));

        if ($issues) {
            throw new CheckoutException($issues);
        }

        $cart->refresh()->load('items.product');

        if ($cart->isEmpty()) {
            throw new CheckoutException(['Your cart is empty.']);
        }

        return DB::transaction(function () use ($cart, $delivery, $user) {
            $subtotal = $cart->subtotal();
            $zone = isset($delivery['delivery_zone_id']) ? DeliveryZone::find($delivery['delivery_zone_id']) : null;
            $deliveryFee = $zone?->fee() ?? app(SettingsService::class)->deliveryFee();

            [$discount, $couponCode] = $this->resolveCoupon($delivery['coupon_code'] ?? null, $subtotal);

            $order = Order::create([
                'user_id' => $user?->id,
                'status' => OrderStatus::PendingPayment,
                'guest_name' => $user ? null : ($delivery['guest_name'] ?? null),
                'guest_phone' => $user ? null : $delivery['phone'],
                'guest_email' => $user ? null : ($delivery['guest_email'] ?? null),
                'delivery_recipient_name' => $delivery['recipient_name'],
                'delivery_phone' => $delivery['phone'],
                'delivery_zone_id' => $zone?->id,
                'delivery_address_line' => $delivery['address_line'],
                'delivery_landmark' => $delivery['landmark'] ?? null,
                'delivery_latitude' => $delivery['latitude'] ?? null,
                'delivery_longitude' => $delivery['longitude'] ?? null,
                'delivery_fee' => $deliveryFee,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'total' => max(0, $subtotal + $deliveryFee - $discount),
                'coupon_code' => $couponCode,
                'notes' => $delivery['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->unit_price * $item->quantity,
                ]);
            }

            $this->stock->reserveForOrder($order->fresh('items'));

            $cart->update(['status' => 'converted']);

            $result = $this->gateway->initiateCheckout($order);

            return ['order' => $order->fresh(), 'redirect_url' => $result['redirect_url']];
        });
    }

    /**
     * @return array{0: float, 1: ?string}
     */
    protected function resolveCoupon(?string $code, float $subtotal): array
    {
        if (blank($code)) {
            return [0.0, null];
        }

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon || ! $coupon->isValid($subtotal)) {
            return [0.0, null];
        }

        return [$coupon->discountFor($subtotal), $coupon->code];
    }
}
