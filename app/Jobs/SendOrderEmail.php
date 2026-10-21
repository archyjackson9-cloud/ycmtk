<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Enums\OrderEmailType;
use App\Mail\OrderEventMail;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers one queued order email (see EmailNotificationService). Retries
 * with backoff on failure; the NotificationLog row records attempts and the
 * final outcome. Safe to run twice - an already-sent log row is skipped.
 */
class SendOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public int $notificationLogId)
    {
        $this->afterCommit();
    }

    /** @return array<int, int> seconds between attempts */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(): void
    {
        $log = NotificationLog::find($this->notificationLogId);

        if (! $log || $log->status === NotificationStatus::Sent) {
            return;
        }

        $order = Order::with(['items', 'deliveryZone', 'user', 'payments'])->find($log->notifiable_id);

        if (! $order) {
            $log->update(['status' => NotificationStatus::Failed, 'provider_response' => 'Order no longer exists.']);

            return;
        }

        $log->increment('attempts');

        try {
            Mail::to($log->recipient)->send(new OrderEventMail($order, OrderEmailType::from($log->type)));
        } catch (Throwable $e) {
            $log->update(['provider_response' => $e->getMessage()]);

            throw $e; // let the queue retry with backoff; failed() runs after the last try
        }

        $log->update(['status' => NotificationStatus::Sent, 'sent_at' => now(), 'provider_response' => null]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('Order email failed permanently', ['notification_log' => $this->notificationLogId, 'error' => $e->getMessage()]);

        NotificationLog::whereKey($this->notificationLogId)
            ->update(['status' => NotificationStatus::Failed, 'provider_response' => $e->getMessage()]);
    }
}
