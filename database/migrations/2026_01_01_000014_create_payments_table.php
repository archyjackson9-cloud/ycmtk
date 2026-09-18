<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment Service (TOR §6.5, §9 Payment entity, §11 "Duplicate payment /
 * double-click on pay -> Idempotency keys with Hubtel prevent duplicate
 * charges"). `reference` is our own idempotency key generated before the
 * customer is sent to Hubtel; `hubtel_transaction_id` is filled in once
 * Hubtel's webhook confirms the transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('hubtel');
            $table->string('reference')->unique();
            $table->string('hubtel_transaction_id')->nullable()->index();
            $table->string('channel')->nullable(); // mtn-gh, vodafone-gh, airteltigo-gh, card
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
