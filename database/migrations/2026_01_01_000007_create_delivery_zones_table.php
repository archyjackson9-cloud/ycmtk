<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilot coverage zone list (TOR §3.1 "Fixed, Super-Admin-configurable
 * delivery fee within the Tarkwa pilot coverage zone", §11 "Delivery
 * address outside pilot zone"). fee_override lets a specific zone differ
 * from the global fixed fee without turning on full zone-based pricing
 * (which stays out of scope per TOR §3.3 until Phase 2+).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('fee_override', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
