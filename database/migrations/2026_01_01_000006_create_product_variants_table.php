<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (TOR §9 ProductVariant entity - grade/weight/pack size with its
 * own SKU, price and stock). Schema is created now so Phase 2 does not
 * require a re-architecture; the storefront/admin do not surface variants
 * until Phase 2 UI is built.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('label'); // e.g. "5kg bag", "Grade A"
            $table->json('attributes')->nullable(); // {"grade":"A","weight":"5kg"}
            $table->decimal('price_override', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
