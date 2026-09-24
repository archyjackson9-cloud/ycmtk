<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\RefundPreference;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enums\PaymentStatus;
use App\Services\OrderService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Customer order history & tracking (TOR §6.4 "Customers track order
 * status in real time"; §8.2 order tracking / order history screens).
 */
class OrderController extends Controller
{
    public function __construct(protected OrderService $orders) {}

    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(10);

        return view('storefront.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        $this->authorizeOrderAccess($request, $order);

        $order->load(['items.product.images', 'statusHistories', 'payments', 'deliveryZone']);

        return view('storefront.orders.show', compact('order'));
    }

    /**
     * JSON poll used by the order page while the customer approves the MoMo
     * prompt on their phone. Asks MTN for the latest status if still pending.
     */
    public function paymentStatus(Request $request, Order $order, PaymentGatewayInterface $gateway): JsonResponse
    {
        $this->authorizeOrderAccess($request, $order);

        $payment = $order->latestPayment();

        if ($payment && $payment->status === PaymentStatus::Pending) {
            $payment = $gateway->refreshStatus($payment);
        }

        return response()->json([
            "payment_status" => $payment?->status->value,
            "order_status" => $order->fresh()->status->value,
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return back()->with('error', 'This order has already progressed - please contact CY-Market to cancel it.');
        }

        try {
            $this->orders->cancel($order, 'Cancelled by customer before payment.', $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('success', 'Your order has been cancelled.');
    }

    /**
     * TOR §6.5/§11 - customer chooses refund vs credit when their order is
     * placed On Hold after payment (stock shortfall discovered post-pay).
     */
    public function resolveHold(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        abort_unless($order->status === OrderStatus::OnHold, 404);

        $data = $request->validate(['preference' => ['required', 'in:refund,credit']]);

        $this->orders->resolveOnHold($order, RefundPreference::from($data['preference']), $request->user());

        return redirect()->route('orders.show', $order)->with('success', 'Thanks - we have recorded your preference.');
    }

    public function trackForm()
    {
        return view('storefront.orders.track');
    }

    public function track(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string'],
            'phone' => ['required', 'string'],
        ]);

        $order = Order::where('order_number', $data['order_number'])
            ->where(function ($q) use ($data) {
                $q->where('delivery_phone', $data['phone'])
                    ->orWhere('guest_phone', $data['phone']);
            })
            ->first();

        if (! $order) {
            return back()->with('error', 'We could not find an order with those details.');
        }

        $request->session()->push('guest_order_ids', $order->id);

        return redirect()->route('orders.show', $order);
    }

    protected function authorizeOrderAccess(Request $request, Order $order): void
    {
        if ($request->user()) {
            $this->authorize('view', $order);

            return;
        }

        abort_unless(in_array($order->id, $request->session()->get('guest_order_ids', []), true), 403);
    }
}
