<?php

namespace App\Services\Notifications;

interface SmsGatewayInterface
{
    /**
     * Send a raw SMS. Returns [success(bool), providerResponse(string)].
     *
     * @return array{0: bool, 1: string}
     */
    public function sendRaw(string $to, string $message): array;

    /**
     * Send one message to many recipients in a single gateway request.
     *
     * @param  array<int, string>  $recipients  numbers already normalised
     * @return array{0: bool, 1: string}
     */
    public function sendBulk(array $recipients, string $message): array;
}
