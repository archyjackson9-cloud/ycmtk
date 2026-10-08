<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEventType;
use App\Mail\OrderEventMail;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Order-tracking-update emails - the email counterpart to every mandatory
 * SMS trigger (TOR §6.6), sent alongside it from OrderService::notify()
 * whenever the order has an email address on file (registered customer's
 * account email, or the optional guest email at checkout). With
 * MAIL_MAILER=log (the default, see config/mail.php) nothing is actually
 * delivered - messages are written to the log, the same "safe by default"
 * pattern SmsNotificationService and the MTN MoMo gateway already use, so
 * this is exercised end-to-end with zero mail credentials. Switching
 * MAIL_MAILER to smtp/ses/postmark/resend in .env makes it live with no
 * code changes.
 */
class EmailNotificationService
{
    /**
     * Returns null (and sends nothing) when the order has no email address
     * - this is an enhancement on top of SMS, not a requirement, since
     * email is optional at checkout.
     */
    public function sendOrderEvent(Order $order, OrderEventType $event): ?NotificationLog
    {
        $to = $order->user?->email ?: $order->guest_email;

        if (blank($to)) {
            return null;
        }

        $bodyText = $this->buildMessage($order, $event);

        $log = NotificationLog::create([
            'notifiable_type' => $order->getMorphClass(),
            'notifiable_id' => $order->getKey(),
            'channel' => NotificationChannel::Email,
            'type' => $event->value,
            'recipient' => $to,
            'message' => $bodyText,
            'status' => NotificationStatus::Pending,
            'attempts' => 1,
        ]);

        try {
            Mail::to($to)->send(new OrderEventMail($order, $event, $bodyText));

            $log->update(['status' => NotificationStatus::Sent, 'sent_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Order-event email failed', ['order' => $order->order_number, 'to' => $to, 'error' => $e->getMessage()]);
            $log->update(['status' => NotificationStatus::Failed, 'provider_response' => $e->getMessage()]);
        }

        return $log->fresh();
    }

    /**
     * Plain-text summary dropped into the email body. Deliberately
     * separate wording from the SMS templates (Admin > Settings): SMS
     * templates are tuned for ~160-character messages with placeholder
     * substitution, while email isn't character-constrained and already
     * shows the order number/total in the table below the summary.
     */
    protected function buildMessage(Order $order, OrderEventType $event): string
    {
        return match ($event) {
            OrderEventType::OrderPlaced => 'Thank you! We have received your order and will confirm it once payment is completed.',
            OrderEventType::PaymentReceived => 'We have received your payment. Thank you!',
            OrderEventType::OrderConfirmed => 'Your order is confirmed and has been queued for processing.',
            OrderEventType::OrderProcessing => 'Your order is being prepared at our farm store.',
            OrderEventType::OrderDispatched => 'Your order has been dispatched for delivery to '.$order->delivery_address_line.'.',
            OrderEventType::OrderDelivered => 'Your order has been delivered. Enjoy your farm-fresh produce!',
            OrderEventType::OrderCancelled => trim('Your order has been cancelled. '.($order->cancelled_reason ?? '')),
            OrderEventType::OrderOnHold => trim('Your order needs your attention - '.($order->on_hold_reason ?? '').' Please choose a refund or credit from your order page.'),
            OrderEventType::PaymentFailed => 'Your payment was not completed. Your cart is still saved - you can try again any time.',
        };
    }
}
