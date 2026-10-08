<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilot "pay by transfer" payment method: the customer pays outside the
 * system (to a published MoMo/bank account) and a staff member confirms
 * the Payment manually once they see it land - see ManualPaymentService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('raw_response');
            $table->foreignId('confirmed_by')->nullable()->after('proof_path')->constrained('users')->nullOnDelete();
            $table->text('note')->nullable()->after('confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['proof_path', 'note']);
        });
    }
};
