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
        $rules = [
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
        }

        // Guests can track this order later without an account (TOR §6.3
        // guest checkout).
        $request->session()->push('guest_order_ids', $result['order']->id);

        return redirect()->away($result['redirect_url']);
    }

    /**
     * GET /checkout/return - the customer's browser lands here after
     * leaving to approve payment on Hubtel (or the sandbox page). The
     * webhook is the source of truth for status, so this is purely
     * informational.
     */
    public function returnFromGateway(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();

        return redirect()->route('orders.show', $payment->order)->with(
            $payment->status === PaymentStatus::Successful ? 'success' : 'info',
            'Thanks! We are confirming your payment - this page will update automatically via SMS and here shortly.'
        );
    }

    /**
     * Sandbox-mode "pay" page (HUBTEL_MODE=sandbox, the default) - lets a
     * tester approve or decline the mock payment so the full order
     * lifecycle can be exercised without live Hubtel credentials.
     */
    public function sandboxPay(string $reference)
    {
        abort_unless(config('hubtel.mode', 'sandbox') !== 'live', 404);

        $payment = Payment::with('order')->where('reference', $reference)->firstOrFail();

        return view('storefront.checkout.sandbox-pay', compact('payment'));
    }

    public function sandboxConfirm(Request $request, string $reference, PaymentGatewayInterface $gateway): RedirectResponse
    {
        abort_unless(config('hubtel.mode', 'sandbox') !== 'live', 404);

        $approve = $request->boolean('approve');
        $payment = Payment::where('reference', $reference)->firstOrFail();

        $gateway->handleCallback([
            'ClientReference' => $reference,
            'TransactionId' => 'SANDBOX-'.strtoupper(uniqid()),
            'Status' => $approve ? 'Success' : 'Failed',
            'Channel' => 'sandbox',
        ]);

        return redirect()->route('checkout.return', $reference);
    }
}
