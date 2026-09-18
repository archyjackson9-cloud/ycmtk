<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEventType;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service (TOR §5.2, §6.6). Every mandatory order-lifecycle
 * SMS trigger from §6.6 goes through sendOrderEvent(). In "log" mode
 * (default, see config/sms.php) messages are written to storage/logs
 * instead of a live gateway so the whole flow is testable with zero
 * credentials; in "live" mode they are POSTed to the configured gateway
 * with a retry policy (TOR §11 "SMS delivery failure").
 */
class SmsNotificationService implements SmsGatewayInterface
{
    public function __construct(protected SettingsService $settings) {}

    public function sendOrderEvent(Order $order, OrderEventType $event, ?string $overridePhone = null): NotificationLog
    {
        $phone = $overridePhone ?? $order->customerPhone();
        $message = $this->buildMessage($order, $event);

        return $this->send($phone, $message, $event, $order);
    }

    public function send(string $to, string $message, OrderEventType $type, ?Model $notifiable = null): NotificationLog
    {
        $log = NotificationLog::create([
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
            'channel' => NotificationChannel::Sms,
            'type' => $type->value,
            'recipient' => $to,
            'message' => $message,
            'status' => NotificationStatus::Pending,
            'attempts' => 0,
        ]);

        $this->attemptDelivery($log);

        return $log->fresh();
    }

    protected function attemptDelivery(NotificationLog $log): void
    {
        $maxAttempts = max(1, (int) config('sms.retry_attempts', 3));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $log->increment('attempts');

            [$success, $response] = $this->sendRaw($log->recipient, $log->message);

            if ($success) {
                $log->update([
                    'status' => NotificationStatus::Sent,
                    'provider_response' => $response,
                    'sent_at' => now(),
                ]);

                return;
            }
        }

        // All retries exhausted (TOR §11 "SMS delivery failure -> Retry
        // mechanism (configurable attempts). Failed SMS logged; Super Admin
        // can view undelivered notifications").
        $log->update([
            'status' => NotificationStatus::Failed,
            'provider_response' => $response ?? 'No response from gateway',
        ]);
    }

    public function sendRaw(string $to, string $message): array
    {
        if (config('sms.mode', 'log') !== 'live') {
            Log::channel('sms')->info('SMS (sandbox/log mode)', ['to' => $to, 'message' => $message]);

            return [true, 'log-mode: simulated delivery'];
        }

        try {
            $response = Http::timeout((int) config('sms.timeout', 15))
                ->withToken(config('sms.api_key'))
                ->post(rtrim((string) config('sms.base_url'), '/').'/send', [
                    'client_id' => config('sms.client_id'),
                    'sender_id' => config('sms.sender_id'),
                    'to' => $to,
                    'message' => $message,
                ]);

            return [$response->successful(), $response->body()];
        } catch (\Throwable $e) {
            Log::channel('sms')->error('SMS send failed', ['to' => $to, 'error' => $e->getMessage()]);

            return [false, $e->getMessage()];
        }
    }

    /**
     * Concise, order-reference-bearing message text per TOR §6.6 ("Messages
     * are concise, include the order reference, and (where relevant) a
     * tracking link or expected timeframe"). Templates are stored per-event
     * as Super-Admin-configurable settings.
     */
    protected function buildMessage(Order $order, OrderEventType $event): string
    {
        $defaults = [
            OrderEventType::OrderPlaced->value => 'CY-Market: Thank you! Your order {order_number} has been received. We will confirm once payment is completed.',
            OrderEventType::PaymentReceived->value => 'CY-Market: Payment received for order {order_number}. Total GHS {total}. Thank you!',
            OrderEventType::OrderConfirmed->value => 'CY-Market: Order {order_number} is confirmed and queued for processing.',
            OrderEventType::OrderProcessing->value => 'CY-Market: Order {order_number} is being prepared at our farm store.',
            OrderEventType::OrderDispatched->value => 'CY-Market: Order {order_number} has been dispatched for delivery to {address}.',
            OrderEventType::OrderDelivered->value => 'CY-Market: Order {order_number} has been delivered. Enjoy your farm-fresh produce!',
            OrderEventType::OrderCancelled->value => 'CY-Market: Order {order_number} has been cancelled. {reason}',
            OrderEventType::OrderOnHold->value => 'CY-Market: Order {order_number} needs attention - {reason} Please reply or call us to choose a refund or credit.',
            OrderEventType::PaymentFailed->value => 'CY-Market: Payment for order {order_number} was not completed. Your cart is still saved - please try again.',
        ];

        $template = $this->settings->smsTemplate($event->value, $defaults[$event->value] ?? 'CY-Market: Update on order {order_number}.');

        return strtr($template, [
            '{order_number}' => $order->order_number,
            '{total}' => number_format((float) $order->total, 2),
            '{address}' => $order->delivery_address_line,
            '{reason}' => $order->on_hold_reason ?? $order->cancelled_reason ?? '',
        ]);
    }
}
