<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order Management & Lifecycle (TOR §6.4, §9 Order entity). Guest checkout
 * is retained alongside registered-account checkout (TOR §6.3), so
 * delivery/contact details are snapshotted directly on the order rather
 * than only referencing an Address record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending_payment');

            // Guest checkout contact snapshot.
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();

            // Delivery snapshot (TOR §6.3 "Choose / enter delivery
            // location").
            $table->string('delivery_recipient_name');
            $table->string('delivery_phone');
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->text('delivery_address_line');
            $table->string('delivery_landmark')->nullable();

            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('coupon_code')->nullable();

            $table->text('notes')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->text('on_hold_reason')->nullable();

            // TOR §6.5/§11 - refund vs credit preference when payment
            // succeeded but the order cannot be fulfilled.
            $table->string('refund_or_credit_preference')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
