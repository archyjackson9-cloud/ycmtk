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
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Single-page checkout (TOR §6.3, §8.1: "Cart -> Delivery -> Payment ->
 * Confirmation" step indicator, guest checkout retained).
 *
 * Pay-by-transfer ("manual") is the only payment method offered for now -
 * MTN MoMo integration (MtnMomoPaymentService, the webhook, the simulate
 * routes below) is complete and tested but dormant: getting real MTN API
 * credentials is on hold, so nothing in the storefront offers it. It's a
 * config/UI change to bring back, not a rebuild - see PaymentGatewayResolver.
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
        $settings = app(SettingsService::class);
        $manualPaymentAvailable = $settings->manualPaymentConfigured();

        return view('storefront.checkout.index', compact('cart', 'issues', 'zones', 'defaultAddress', 'manualPaymentAvailable') + [
            'accountName' => $settings->manualPaymentAccountName(),
            'accountNumber' => $settings->manualPaymentAccountNumber(),
            'network' => $settings->manualPaymentNetwork(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! app(SettingsService::class)->manualPaymentConfigured()) {
            // No live payment method right now - better to stop here with a
            // clear message than let staff discover it via a broken order.
            return redirect()->route('checkout.index')->with('error', 'Checkout is not available yet - payment details have not been configured. (Admin: set this up in Settings > "Pay by Transfer".)');
        }

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
            $rules['guest_email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);
        // Pay-by-transfer is the only method offered - not a client-supplied
        // value, so there's nothing for a tampered request to override.
        $data['payment_method'] = 'manual';

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

    /**
     * GET /checkout/manual-pay/{reference} - the "pay by transfer"
     * instructions page: account details, the order reference to quote,
     * and an optional proof-of-payment upload. Nothing here confirms the
     * order; a staff member does that from Admin > Payments once they see
     * the transfer land (see ManualPaymentService).
     */
    public function manualPay(string $reference)
    {
        $payment = Payment::with('order')->where('reference', $reference)->where('provider', 'manual')->firstOrFail();
        $settings = app(SettingsService::class);

        return view('storefront.checkout.manual-pay', [
            'payment' => $payment,
            'accountName' => $settings->manualPaymentAccountName(),
            'accountNumber' => $settings->manualPaymentAccountNumber(),
            'network' => $settings->manualPaymentNetwork(),
            'instructions' => $settings->manualPaymentInstructions(),
        ]);
    }

    /**
     * Optional - a screenshot/receipt attached here doesn't change the
     * payment's status by itself, it just gives staff something to match
     * against when they confirm it from Admin > Payments.
     */
    public function manualProofUpload(Request $request, string $reference): RedirectResponse
    {
        $payment = Payment::where('reference', $reference)->where('provider', 'manual')->firstOrFail();

        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->route('orders.show', $payment->order)->with('info', 'This payment has already been resolved.');
        }

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store('payment-proofs', 'public');
        $payment->update(['proof_path' => $path]);

        return redirect()->route('checkout.manual-pay', $reference)->with('success', 'Thanks! We will confirm your payment shortly.');
    }
}
