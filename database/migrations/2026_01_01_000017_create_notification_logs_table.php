<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record of SMS/email/push sent and delivery status (TOR §9
 * NotificationLog entity; §11 "SMS delivery failure -> Failed SMS logged;
 * Super Admin can view undelivered notifications").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('notifiable'); // typically an Order, sometimes a User
            $table->string('channel'); // sms, email, push
            $table->string('type'); // OrderEventType value
            $table->string('recipient');
            $table->text('message');
            $table->string('status')->default('pending'); // pending, sent, delivered, failed
            $table->text('provider_response')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
