<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Services\Notifications\SmsNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers queued SMS notification-log rows (one order SMS, or a chunk of a
 * custom broadcast). Retries with backoff; each log row records attempts and
 * the final outcome. Safe to run twice - rows already Sent/Failed are skipped.
 */
class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    /** @param array<int, int> $notificationLogIds */
    public function __construct(public array $notificationLogIds)
    {
        $this->tries = max(1, (int) config('sms.retry_attempts', 4));
        $this->afterCommit();
    }

    /** @return array<int, int> seconds between attempts */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(SmsNotificationService $sms): void
    {
        $logs = NotificationLog::whereIn('id', $this->notificationLogIds)
            ->where('status', NotificationStatus::Pending)
            ->get();

        if ($logs->isEmpty()) {
            return;
        }

        if (! $sms->deliver($logs)) {
            throw new \RuntimeException('SMS gateway did not accept the message(s).'); // retry with backoff
        }
    }

    public function failed(Throwable $e): void
    {
        Log::channel('sms')->error('SMS failed permanently', ['logs' => $this->notificationLogIds, 'error' => $e->getMessage()]);

        NotificationLog::whereIn('id', $this->notificationLogIds)
            ->where('status', NotificationStatus::Pending)
            ->update(['status' => NotificationStatus::Failed]);
    }
}
