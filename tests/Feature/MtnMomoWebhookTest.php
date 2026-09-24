<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCymarketFixtures;
use Tests\TestCase;

/**
 * Server-to-server MTN MoMo payment callback (TOR §5.2 Payment Service
 * "webhook handling, idempotency"; §11 "Duplicate payment / double-click
 * on pay -> Idempotency keys prevent duplicate charges").
 * CSRF is excepted for this route in bootstrap/app.php, matching a real
 * server-to-server callback.
 */
class MtnMomoWebhookTest extends TestCase
{
    use CreatesCymarketFixtures, RefreshDatabase;

    protected function pendingPayment(): Payment
    {
        $zone = $this->makeDeliveryZone();
        $product = $this->makeProduct(['available_quantity' => 10]);

        $order = Order::create([
            'status' => OrderStatus::PendingPayment,
            'delivery_recipient_name' => 'Test Customer',
            'delivery_phone' => '+233200000001',
            'delivery_zone_id' => $zone->id,
            'delivery_address_line' => '1 Test Street',
            'delivery_fee' => (float) $zone->fee_override,
            'subtotal' => 40,
            'total' => 40 + (float) $zone->fee_override,
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

        return $order->payments()->create([
            'provider' => 'mtn_momo',
            'amount' => $order->total,
            'status' => PaymentStatus::Pending,
        ]);
    }

    public function test_a_successful_webhook_confirms_the_order(): void
    {
        $payment = $this->pendingPayment();

        $response = $this->postJson('/webhooks/mtn-momo', [
            'externalId' => $payment->reference,
            'financialTransactionId' => 'MOMO-TEST-001',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'reference' => $payment->reference]);

        $this->assertSame(PaymentStatus::Successful, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $payment->fresh()->order->status);
        $this->assertSame('MOMO-TEST-001', $payment->fresh()->provider_transaction_id);
    }

    public function test_a_failed_webhook_marks_the_payment_failed_without_touching_the_order_status(): void
    {
        $payment = $this->pendingPayment();

        $response = $this->postJson('/webhooks/mtn-momo', [
            'externalId' => $payment->reference,
            'financialTransactionId' => 'MOMO-TEST-002',
            'status' => 'FAILED',
        ]);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(OrderStatus::PendingPayment, $payment->fresh()->order->status);
    }

    public function test_a_webhook_for_an_unknown_reference_is_reported_and_does_not_crash(): void
    {
        $response = $this->postJson('/webhooks/mtn-momo', [
            'externalId' => 'PAY-DOES-NOT-EXIST',
            'financialTransactionId' => 'MOMO-TEST-003',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['status' => 'unknown_reference']);
    }

    public function test_a_replayed_webhook_is_idempotent(): void
    {
        $payment = $this->pendingPayment();
        $payload = [
            'externalId' => $payment->reference,
            'financialTransactionId' => 'MOMO-TEST-004',
            'status' => 'SUCCESSFUL',
        ];

        $this->postJson('/webhooks/mtn-momo', $payload)->assertOk();
        $order = $payment->fresh()->order;
        $historyCountAfterFirst = $order->statusHistories()->count();

        // MTN (or a reconciliation job) replays the same webhook.
        $this->postJson('/webhooks/mtn-momo', $payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame($historyCountAfterFirst, $order->fresh()->statusHistories()->count(), 'A replayed webhook must not create a second status transition.');
    }
}
