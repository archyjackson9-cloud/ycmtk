<?php

namespace App\Services\Notifications;

use App\Models\NotificationLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Admin-written SMS: one customer or a broadcast to every customer with a
 * valid phone number. Each recipient gets a NotificationLog row (type
 * "custom") and delivery runs through the same queued SendSms job as order
 * SMS, in chunks of config('sms.bulk_chunk_size') per gateway request.
 */
class CustomSmsService
{
    public function __construct(protected SmsNotificationService $sms) {}

    /** Customers (storefront accounts) that have any phone value at all. */
    public function customersQuery(bool $onlyOrdered = false): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', config('cymarket.roles.customer')))
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($onlyOrdered, fn (Builder $q) => $q->whereHas('orders'))
            ->orderBy('id');
    }

    /**
     * Number of distinct, valid phone numbers a broadcast would reach.
     *
     * @return array{recipients: int, skipped: int}
     */
    public function countRecipients(bool $onlyOrdered = false): array
    {
        $recipients = 0;
        $skipped = 0;
        $seen = [];

        foreach ($this->customersQuery($onlyOrdered)->select(['id', 'phone'])->cursor() as $user) {
            $number = PhoneNumber::toArkesel($user->phone);

            if (! $number || isset($seen[$number])) {
                $skipped++;

                continue;
            }

            $seen[$number] = true;
            $recipients++;
        }

        return ['recipients' => $recipients, 'skipped' => $skipped];
    }

    /** {name} -> customer's first name (or "Customer"). */
    public function render(string $template, ?string $name): string
    {
        $first = Str::of((string) $name)->trim()->before(' ')->toString();

        return str_replace('{name}', $first !== '' ? $first : 'Customer', $template);
    }

    /** Send to one chosen number (optionally linked to a customer). */
    public function sendToOne(string $phone, string $message, ?User $user = null): ?NotificationLog
    {
        return $this->sms->send($phone, $this->render($message, $user?->name), SmsNotificationService::TYPE_CUSTOM, $user);
    }

    /**
     * Logs one row per customer and queues delivery in chunks.
     *
     * @return array{queued: int, skipped: int}
     */
    public function broadcast(string $message, bool $onlyOrdered = false): array
    {
        $chunkSize = max(1, (int) config('sms.bulk_chunk_size', 100));
        $queued = 0;
        $skipped = 0;
        $seen = [];
        $batch = [];

        foreach ($this->customersQuery($onlyOrdered)->cursor() as $user) {
            $number = PhoneNumber::toArkesel($user->phone);

            if (! $number || isset($seen[$number])) {
                $skipped++;

                continue;
            }

            $seen[$number] = true;

            $log = $this->sms->record($number, $this->render($message, $user->name), SmsNotificationService::TYPE_CUSTOM, $user);

            if ($log) {
                $batch[] = $log;
                $queued++;
            }

            if (count($batch) >= $chunkSize) {
                $this->sms->queue($batch);
                $batch = [];
            }
        }

        $this->sms->queue($batch);

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Rough SMS cost: 160 chars per single GSM message (153 when split), or
     * 70 (67) once any non-GSM character such as an emoji is used.
     *
     * @return array{length: int, segments: int, unicode: bool}
     */
    public static function segments(string $message): array
    {
        $length = mb_strlen($message);
        $unicode = (bool) preg_match('/[^\x{0A}\x{0D}\x{20}-\x{7E}£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà€]/u', $message);

        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;
        $segments = $length === 0 ? 0 : ($length <= $single ? 1 : (int) ceil($length / $multi));

        return ['length' => $length, 'segments' => $segments, 'unicode' => $unicode];
    }
}
