<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilot payment provider changes from Hubtel to MTN MoMo. Existing rows keep
 * provider = 'hubtel' as history; only the columns are generalised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('hubtel_transaction_id', 'provider_transaction_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->default('mtn_momo')->change();
            $table->uuid('provider_reference')->nullable()->unique()->after('reference');
            $table->string('payer_phone', 20)->nullable()->after('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider_reference']);
            $table->dropColumn(['provider_reference', 'payer_phone']);
            $table->string('provider')->default('hubtel')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('provider_transaction_id', 'hubtel_transaction_id');
        });
    }
};
