<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\OrderEventType;
use App\Jobs\SendSms;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\SettingsService;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service (TOR §5.2, §6.6). Every mandatory order-lifecycle
 * SMS trigger from §6.6 goes through sendOrderEvent(), and admin custom
 * messages through queue(). Nothing is sent inline: each call records a
 * pending NotificationLog row and queues a SendSms job (after the
 * surrounding transaction commits), so a slow or failing gateway never
 * blocks checkout or an admin status change. The job retries with backoff
 * (TOR §11 "SMS delivery failure") and the log row keeps attempts/outcome.
 *
 * In "log" mode (default, see config/sms.php) messages are written to
 * storage/logs instead of Arkesel so the whole flow is testable with zero
 * credentials; in "live" mode they are POSTed to the Arkesel v2 API.
 * Requires a running queue worker in production when QUEUE_CONNECTION is
 * not "sync".
 */
class SmsNotificationService implements SmsGatewayInterface
{
    /** NotificationLog.type used for admin-written (non order-event) SMS. */
    public const TYPE_CUSTOM = 'custom';

    public function __construct(protected SettingsService $settings) {}

    /**
     * Returns null when an SMS with the same dedupe key was already recorded
     * (webhook replays etc. must not text the customer twice).
     */
    public function sendOrderEvent(Order $order, OrderEventType $event, ?string $overridePhone = null, ?string $dedupeKey = null): ?NotificationLog
    {
        $phone = $overridePhone ?? $order->customerPhone();
        $message = $this->buildMessage($order, $event);

        return $this->send($phone, $message, $event, $order, $dedupeKey);
    }

    /**
     * Records the SMS and queues its delivery. A missing/invalid phone number
     * is logged as Failed (never an exception, never a job).
     */
    public function send(?string $to, string $message, OrderEventType|string $type, ?Model $notifiable = null, ?string $dedupeKey = null): ?NotificationLog
    {
        $log = $this->record($to, $message, $type, $notifiable, $dedupeKey);

        if ($log && $log->status === NotificationStatus::Pending) {
            $this->queue([$log]);
        }

        return $log;
    }

    /**
     * Creates the log row without queueing it (broadcasts record many rows
     * and then queue them in chunks).
     */
    public function record(?string $to, string $message, OrderEventType|string $type, ?Model $notifiable = null, ?string $dedupeKey = null): ?NotificationLog
    {
        if ($dedupeKey && NotificationLog::where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $number = PhoneNumber::toArkesel($to);

        try {
            return NotificationLog::create([
                'notifiable_type' => $notifiable?->getMorphClass(),
                'notifiable_id' => $notifiable?->getKey(),
                'channel' => NotificationChannel::Sms,
                'type' => $type instanceof OrderEventType ? $type->value : $type,
                'dedupe_key' => $dedupeKey,
                'recipient' => $number ?? (string) $to,
                'message' => $message,
                'status' => $number ? NotificationStatus::Pending : NotificationStatus::Failed,
                'provider_response' => $number ? null : 'No valid Ghana phone number - SMS not sent.',
                'attempts' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null; // lost a race with an identical concurrent request
        }
    }

    /**
     * Queues one SendSms job for the given pending log rows, once the current
     * transaction (if any) has committed. A queue outage must never turn an
     * already-committed order into an error page, so it only marks the rows
     * Failed.
     *
     * @param  iterable<int, NotificationLog>  $logs
     */
    public function queue(iterable $logs): void
    {
        $ids = collect($logs)->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        DB::afterCommit(function () use ($ids) {
            try {
                SendSms::dispatch($ids);
            } catch (\Throwable $e) {
                // With QUEUE_CONNECTION=sync the job's own failure surfaces here
                // (failed() already marked the rows); otherwise the dispatch failed.
                Log::channel('sms')->error('SMS job failed or could not be queued', ['logs' => $ids, 'error' => $e->getMessage()]);

                NotificationLog::whereIn('id', $ids)
                    ->where('status', NotificationStatus::Pending)
                    ->update(['status' => NotificationStatus::Failed, 'provider_response' => 'Could not queue: '.$e->getMessage()]);
            }
        });
    }

    /**
     * One delivery attempt for pending log rows (called by the SendSms job).
     * Rows sharing the same text go out in a single gateway request. Rows
     * that succeed become Sent; the rest stay Pending with the provider's
     * answer so the job can retry them. Returns true when all were accepted.
     *
     * @param  Collection<int, NotificationLog>  $logs
     */
    public function deliver(Collection $logs): bool
    {
        $allOk = true;

        foreach ($logs->groupBy('message') as $message => $group) {
            $group->each(fn (NotificationLog $log) => $log->increment('attempts'));

            $recipients = $group->pluck('recipient')->unique()->values()->all();

            [$success, $response] = count($recipients) === 1
                ? $this->sendRaw($recipients[0], (string) $message)
                : $this->sendBulk($recipients, (string) $message);

            foreach ($group as $log) {
                $log->update($success
                    ? ['status' => NotificationStatus::Sent, 'provider_response' => $response, 'sent_at' => now()]
                    : ['provider_response' => $response]);
            }

            $allOk = $allOk && $success;
        }

        return $allOk;
    }

    public function sendRaw(string $to, string $message): array
    {
        return $this->sendBulk([$to], $message);
    }

    public function sendBulk(array $recipients, string $message): array
    {
        if (config('sms.mode', 'log') !== 'live') {
            Log::channel('sms')->info('SMS (sandbox/log mode)', ['to' => $recipients, 'message' => $message]);

            return [true, 'log-mode: simulated delivery'];
        }

        if (blank(config('sms.api_key'))) {
            return [false, 'SMS_ARKESEL_API_KEY is not configured.'];
        }

        try {
            $response = Http::timeout((int) config('sms.timeout', 15))
                ->withHeaders(['api-key' => (string) config('sms.api_key')])
                ->acceptJson()
                ->asJson()
                ->post(rtrim((string) config('sms.base_url'), '/').'/sms/send', [
                    'sender' => config('sms.sender_id'),
                    'message' => $message,
                    'recipients' => array_values($recipients),
                ]);

            // Arkesel can answer HTTP 200 with an error body, so the JSON
            // "status" has to be checked as well.
            $ok = $response->successful() && $response->json('status') === 'success';

            return [$ok, $response->body()];
        } catch (\Throwable $e) {
            Log::channel('sms')->error('SMS send failed', ['to' => $recipients, 'error' => $e->getMessage()]);

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
            OrderEventType::OrderPlaced->value => 'CY-Market: Thank you! Your order {order_number} has been received. We will confirm once payment is completed.{delivery}',
            OrderEventType::PaymentReceived->value => 'CY-Market: Payment received for order {order_number}. Total GHS {total}. Thank you!',
            OrderEventType::OrderConfirmed->value => 'CY-Market: Order {order_number} is confirmed and queued for processing.{delivery}',
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
            '{delivery}' => $order->scheduledDeliveryLabel() ? ' Delivery: '.$order->scheduledDeliveryLabel().'.' : '',
            '{reason}' => $order->on_hold_reason ?? $order->cancelled_reason ?? '',
        ]);
    }
}
