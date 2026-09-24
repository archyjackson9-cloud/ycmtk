<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'reference', 'provider_transaction_id', 'provider_reference', 'payer_phone', 'channel',
        'amount', 'status', 'raw_request', 'raw_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->reference ??= static::generateReference();
        });
    }

    /**
     * Idempotency key for the payment gateway (TOR §11 "Duplicate payment /
     * double-click on pay -> Idempotency keys prevent duplicate
     * charges").
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'PAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
