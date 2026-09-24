<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Payments\MtnMomoPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * MTN MoMo live-mode behaviour against a faked MTN API: request-to-pay
 * initiation, status polling, and the "never trust the callback body" rule.
 */
class MtnMomoLiveTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'momo.mode' => 'live',
            'momo.base_url' => 'https://momo.test',
            'momo.subscription_key' => 'sub-key',
            'momo.api_user' => 'user',
            'momo.api_key' => 'key',
            'momo.currency' => 'GHS',
        ]);
        Cache::forget('momo.collection.token');
    }

    protected function pendingOrder(): Order
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $order = Order::create([
            'status' => OrderStatus::PendingPayment,
            'delivery_recipient_name' => 'Test Customer',
            'delivery_phone' => '0241234567',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => 10,
            'subtotal' => 40,
            'total' => 50,
            'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'unit_price' => 20,
            'quantity' => 2,
            'line_total' => 40,
        ]);

        return $order;
    }

    public function test_it_normalises_ghana_numbers(): void
    {
        $this->assertSame('233241234567', MtnMomoPaymentService::normalizeMsisdn('024 123 4567'));
        $this->assertSame('233241234567', MtnMomoPaymentService::normalizeMsisdn('+233241234567'));
        $this->assertSame('233241234567', MtnMomoPaymentService::normalizeMsisdn('233241234567'));
    }

    public function test_initiating_sends_a_request_to_pay_and_leaves_the_payment_pending(): void
    {
        Http::fake([
            'momo.test/collection/token/' => Http::response(['access_token' => 'tok']),
            'momo.test/collection/v1_0/requesttopay' => Http::response('', 202),
        ]);

        $order = $this->pendingOrder();
        $result = app(MtnMomoPaymentService::class)->initiateCheckout($order, '024 123 4567');

        $payment = $result['payment'];
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('233241234567', $payment->payer_phone);
        $this->assertSame(route('checkout.return', $payment->reference), $result['redirect_url']);

        Http::assertSent(fn ($request) => $request->url() === 'https://momo.test/collection/v1_0/requesttopay'
            && $request['payer']['partyId'] === '233241234567'
            && $request['externalId'] === $payment->reference
            && $request->hasHeader('X-Reference-Id', $payment->provider_reference));
    }

    public function test_a_rejected_request_fails_the_payment_and_throws(): void
    {
        Http::fake([
            'momo.test/collection/token/' => Http::response(['access_token' => 'tok']),
            'momo.test/collection/v1_0/requesttopay' => Http::response(['message' => 'bad'], 400),
        ]);

        $order = $this->pendingOrder();

        $this->expectException(\RuntimeException::class);

        try {
            app(MtnMomoPaymentService::class)->initiateCheckout($order);
        } finally {
            $this->assertSame(PaymentStatus::Failed, $order->payments()->first()->status);
        }
    }

    public function test_callback_is_verified_with_mtn_and_not_trusted_on_its_own(): void
    {
        // What MTN currently reports for the request; changed mid-test.
        $mtnStatus = ['status' => 'PENDING'];

        Http::fake([
            'momo.test/collection/token/' => Http::response(['access_token' => 'tok']),
            'momo.test/collection/v1_0/requesttopay' => Http::response('', 202),
            'momo.test/collection/v1_0/requesttopay/*' => function () use (&$mtnStatus) {
                return Http::response($mtnStatus);
            },
        ]);

        $order = $this->pendingOrder();
        $payment = app(MtnMomoPaymentService::class)->initiateCheckout($order)['payment'];

        // A forged "SUCCESSFUL" callback while MTN still says PENDING.
        $this->postJson('/webhooks/mtn-momo', ['externalId' => $payment->reference, 'status' => 'SUCCESSFUL'])->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        // MTN now genuinely reports success.
        $mtnStatus = ['status' => 'SUCCESSFUL', 'financialTransactionId' => 'FIN-1'];

        $this->putJson('/webhooks/mtn-momo', ['externalId' => $payment->reference, 'status' => 'SUCCESSFUL'])->assertOk();

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame('FIN-1', $payment->fresh()->provider_transaction_id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }
}
