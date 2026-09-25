<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Payment;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single-page checkout (TOR §6.3, §8.1: "Cart -> Delivery -> Payment ->
 * Confirmation" step indicator, guest checkout retained).
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $carts,
        protected CheckoutService $checkout,
    ) {}

    public function index(Request $request)
    {
        $cart = $this->carts->currentCart($request->user(), $request->session()->getId());
        $cart->load('items.product.images');

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $issues = $this->carts->validateForCheckout($cart);
        $cart->refresh()->load('items.product.images');

        $zones = DeliveryZone::active()->orderBy('name')->get();
        $defaultAddress = $request->user()?->defaultAddress;

        return view('storefront.checkout.index', compact('cart', 'issues', 'zones', 'defaultAddress'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Accept "024 123 4567" style input for the MoMo number.
        $request->merge(['momo_number' => preg_replace('/[\s-]+/', '', (string) $request->input('momo_number')) ?: null]);

        $rules = [
            'momo_number' => ['nullable', 'regex:/^(\+?233|0)?[235]\d{8}$/'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'delivery_zone_id' => ['nullable', 'exists:delivery_zones,id'],
            'address_line' => ['required', 'string', 'max:1000'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ];

        if (! $request->user()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
            $rules['guest_email'] = ['nullable', 'email', 'max:255'];
        }

        $data = $request->validate($rules);

        $cart = $this->carts->currentCart($request->user(), $request->session()->getId());

        try {
            $result = $this->checkout->checkout($cart, $data, $request->user());
        } catch (CheckoutException $e) {
            return redirect()->route('checkout.index')->with('error', $e->getMessage())->with('checkout_issues', $e->issues());
        } catch (\RuntimeException $e) {
            // Gateway unreachable/rejected: the order transaction rolled back,
            // so the cart is intact and the customer can simply retry.
            return redirect()->route('checkout.index')->withInput()->with('error', $e->getMessage());
        }

        // Guests can track this order later without an account (TOR §6.3
        // guest checkout).
        $request->session()->push('guest_order_ids', $result['order']->id);

        return redirect()->away($result['redirect_url']);
    }

    /**
     * GET /checkout/return - the customer's browser lands here after
     * being asked to approve the MoMo prompt on their phone (or the simulate
     * page). The order page polls for the final status.
     */
    public function returnFromGateway(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        return redirect()->route('orders.show', $payment->order)->with(
            $payment->status === PaymentStatus::Successful ? 'success' : 'info',
            match ($payment->status) {
                PaymentStatus::Successful => 'Payment confirmed - thank you! Your order is now being prepared.',
                PaymentStatus::Failed => 'The payment was not completed. You can place the order again when ready.',
                default => 'Check your phone and approve the MTN MoMo prompt with your PIN - this page updates automatically once payment is confirmed.',
            }
        );
    }

    /**
     * Simulate-mode "pay" page (MOMO_MODE=simulate, the default) - lets a
     * tester approve or decline the mock payment so the full order
     * lifecycle can be exercised without live MTN credentials.
     */
    public function sandboxPay(string $reference)
    {
        abort_unless(config('momo.mode', 'simulate') !== 'live', 404);

        $payment = Payment::with('order')->where('reference', $reference)->firstOrFail();

        return view('storefront.checkout.sandbox-pay', compact('payment'));
    }

    public function sandboxConfirm(Request $request, string $reference, PaymentGatewayInterface $gateway): RedirectResponse
    {
        abort_unless(config('momo.mode', 'simulate') !== 'live', 404);

        $approve = $request->boolean('approve');
        $payment = Payment::where('reference', $reference)->firstOrFail();

        $gateway->handleCallback([
            'externalId' => $reference,
            'financialTransactionId' => 'SIM-'.strtoupper(uniqid()),
            'status' => $approve ? 'SUCCESSFUL' : 'FAILED',
        ]);

        return redirect()->route('checkout.return', $reference);
    }
}
