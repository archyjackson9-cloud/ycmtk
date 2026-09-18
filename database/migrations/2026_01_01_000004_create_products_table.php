<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product catalogue (TOR §6.1 Product Catalogue & Multi-Category Management,
 * §9 Product entity). Every product is CY-Market's own farm-grown produce -
 * there is no seller/vendor foreign key (TOR §3.3 single-seller model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();

            // Unit of measurement, e.g. kg, bag, crate, tuber, bunch.
            $table->string('unit_of_measurement')->default('unit');

            $table->decimal('selling_price', 10, 2);
            $table->decimal('wholesale_price', 10, 2)->nullable();

            $table->unsignedInteger('min_order_quantity')->default(1);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();

            // Farm-to-table traceability (Expansion §6.1).
            $table->string('production_location')->nullable();
            $table->string('packaging_type')->nullable();
            $table->string('weight')->nullable();

            // Seasonal availability (TOR §6.1 "Seasonal availability can
            // hide or flag products outside season").
            $table->boolean('is_seasonal')->default(false);
            $table->unsignedTinyInteger('season_start_month')->nullable();
            $table->unsignedTinyInteger('season_end_month')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Denormalised counters for discovery rails (Expansion §6.2) -
            // safe to keep at zero/updated lazily in Phase 1.
            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('ratings_count')->default(0);

            // Bullet-point specs / spec sheet links (Expansion "rich content
            // fields").
            $table->json('specifications')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
