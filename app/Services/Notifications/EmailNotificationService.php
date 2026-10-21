<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEmailType;
use App\Jobs\SendOrderEmail;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\SettingsService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Customer order emails (order placed, every status change, payment not
 * completed). Nothing is sent from here directly: each call records a
 * pending NotificationLog row (inside the caller's transaction, so it rolls
 * back with it) and queues a SendOrderEmail job to run once that
 * transaction has committed. A slow or failing mail server therefore never
 * blocks checkout or holds order/stock row locks, and delivery failures are
 * retried by the queue worker and recorded on the log row.
 *
 * Idempotent: every automatic email carries a dedupe key (order, status
 * history row or payment) backed by a unique index, so a repeated
 * webhook/callback can never queue the same email twice.
 *
 * With MAIL_MAILER=log (the default) nothing is delivered - messages are
 * written to the log - and switching MAIL_MAILER to smtp/ses/etc. in .env
 * makes it live with no code changes. Requires a running queue worker in
 * production (QUEUE_CONNECTION must not be "sync").
 */
class EmailNotificationService
{
    public function __construct(protected SettingsService $settings) {}

    public function queueOrderPlaced(Order $order): ?NotificationLog
    {
        return $this->queue($order, OrderEmailType::Placed, 'email:placed:'.$order->id);
    }

    /**
     * Called from OrderService::transition(), the single place every status
     * change passes through. Does nothing if the status did not change.
     */
    public function queueStatusChange(Order $order, OrderStatusHistory $history): ?NotificationLog
    {
        if ($history->from_status === $history->to_status) {
            return null;
        }

        $type = OrderEmailType::forStatus($history->to_status);

        return $type ? $this->queue($order, $type, 'email:status:'.$history->id) : null;
    }

    public function queuePaymentFailed(Order $order, Payment $payment): ?NotificationLog
    {
        return $this->queue($order, OrderEmailType::PaymentFailed, 'email:payment-failed:'.$payment->id);
    }

    /**
     * Admin "resend": re-sends the email for the order's current status.
     * Deliberately has no dedupe key so staff can resend on demand.
     */
    public function resend(Order $order): ?NotificationLog
    {
        $type = OrderEmailType::forStatus($order->status) ?? OrderEmailType::Placed;

        return $this->queue($order, $type, null, force: true);
    }

    public function recipientFor(Order $order): ?string
    {
        $to = $order->user?->email ?: $order->guest_email;

        return filled($to) ? $to : null;
    }

    protected function queue(Order $order, OrderEmailType $type, ?string $dedupeKey, bool $force = false): ?NotificationLog
    {
        if (! $force && ! $this->settings->orderEmailsEnabled()) {
            return null;
        }

        $to = $this->recipientFor($order);

        if (! $to) {
            return null;
        }

        if ($dedupeKey && NotificationLog::where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        try {
            $log = NotificationLog::create([
                'notifiable_type' => $order->getMorphClass(),
                'notifiable_id' => $order->getKey(),
                'channel' => NotificationChannel::Email,
                'type' => $type->value,
                'dedupe_key' => $dedupeKey,
                'recipient' => $to,
                'message' => $type->label().' - order '.$order->order_number,
                'status' => NotificationStatus::Pending,
                'attempts' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // lost a race with an identical concurrent request
        }

        // After commit, so the worker never sees a half-written order, and
        // a rolled-back transaction sends nothing. Wrapped so a queue outage
        // can never turn an already-committed order into an error page.
        DB::afterCommit(function () use ($log, $order) {
            try {
                SendOrderEmail::dispatch($log->id);
            } catch (\Throwable $e) {
                Log::error('Could not queue order email', ['order' => $order->order_number, 'error' => $e->getMessage()]);
                $log->update(['status' => NotificationStatus::Failed, 'provider_response' => 'Could not queue: '.$e->getMessage()]);
            }
        });

        return $log;
    }

    /**
     * Plain-language copy for each email: headline, explanation and next
     * steps. Rendered by both the HTML and the plain-text template.
     *
     * @return array{headline: string, intro: string, next: string}
     */
    public function content(Order $order, OrderEmailType $type): array
    {
        $schedule = $order->scheduledDeliveryLabel();
        $scheduleLine = $schedule ? " We are scheduled to deliver on {$schedule}." : '';

        return match ($type) {
            OrderEmailType::Placed => [
                'headline' => 'Thank you - we have received your order',
                'intro' => 'Your order is placed and your items are reserved for you.'.($schedule ? " Scheduled delivery: {$schedule}." : ''),
                'next' => 'Please send the exact total using the payment details below, with your order number as the reference. We will email you again as soon as we confirm your payment.',
            ],
            OrderEmailType::Confirmed => [
                'headline' => 'Your payment is confirmed',
                'intro' => 'We have received your payment and your order is confirmed.'.$scheduleLine,
                'next' => 'Nothing more is needed from you. Our team will start preparing your order shortly and we will keep you updated.',
            ],
            OrderEmailType::Processing => [
                'headline' => 'We are preparing your order',
                'intro' => 'Our team is picking and packing your farm-fresh items.'.$scheduleLine,
                'next' => 'Please keep your phone nearby - our rider may call you on delivery.',
            ],
            OrderEmailType::Dispatched => [
                'headline' => 'Your order is on its way',
                'intro' => 'Your order is out for delivery to '.$order->delivery_address_line.'.',
                'next' => 'Please make sure someone is available to receive it and keep your phone on.',
            ],
            OrderEmailType::Delivered => [
                'headline' => 'Your order has been delivered',
                'intro' => 'Your order has been delivered. We hope you enjoy your farm-fresh produce!',
                'next' => 'If anything is missing or not as expected, please contact us right away and we will put it right.',
            ],
            OrderEmailType::Completed => [
                'headline' => 'Your order is complete',
                'intro' => 'Your order is now complete. Thank you for shopping with us.',
                'next' => 'We would love to see you again - your order details remain available through the link below.',
            ],
            OrderEmailType::Cancelled => [
                'headline' => 'Your order has been cancelled',
                'intro' => 'Your order has been cancelled.'.($order->cancelled_reason ? ' Reason: '.$order->cancelled_reason : ''),
                'next' => 'If you already paid, we will arrange your refund - contact us if you have not heard from us. You are welcome to place a new order any time.',
            ],
            OrderEmailType::OnHold => [
                'headline' => 'Your order needs your attention',
                'intro' => 'Your order is on hold.'.($order->on_hold_reason ? ' '.$order->on_hold_reason : ''),
                'next' => 'Please open your order page and choose either a full refund or credit towards your next delivery.',
            ],
            OrderEmailType::PaymentFailed => [
                'headline' => 'Your payment was not completed',
                'intro' => 'We could not confirm a payment for this order, so it has not been marked as paid.',
                'next' => 'If you have paid, contact us with your order number and we will check. Otherwise you can place the order again - your items are still available while stock lasts.',
            ],
        };
    }
}
