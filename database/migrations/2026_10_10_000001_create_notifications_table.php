<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard notifications table (the `database` channel) - powers
 * both the storefront bell for registered customers (OrderStatusUpdated)
 * and the Filament admin panel's built-in notification bell for staff
 * (NewOrderPlaced). Distinct from `notification_logs`, which is the
 * SMS/email delivery audit trail (TOR §9/§11) - this one is read/unread
 * state for the recipient themselves, not a delivery record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
