<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'sku', 'description', 'short_description',
        'unit_of_measurement', 'selling_price', 'wholesale_price',
        'min_order_quantity', 'available_quantity', 'reserved_quantity', 'low_stock_threshold',
        'production_location', 'packaging_type', 'weight',
        'is_seasonal', 'season_start_month', 'season_end_month',
        'is_active', 'is_featured', 'specifications',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'min_order_quantity' => 'integer',
            'available_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_seasonal' => 'boolean',
            'season_start_month' => 'integer',
            'season_end_month' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sold_count' => 'integer',
            'views_count' => 'integer',
            'average_rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'specifications' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name).'-'.Str::lower(Str::random(5));
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'approved');
    }

    /**
     * Stock that is actually sellable right now = on-hand minus what is
     * softly reserved by in-flight payments (TOR §11 stock reservation).
     */
    public function getSellableQuantityAttribute(): int
    {
        return max(0, $this->available_quantity - $this->reserved_quantity);
    }

    public function getIsLowStockAttribute(): bool
    {
        $threshold = $this->low_stock_threshold ?? config('cymarket.default_low_stock_threshold');

        return $this->sellable_quantity <= $threshold && $this->sellable_quantity > 0;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->sellable_quantity <= 0;
    }

    /**
     * Seasonal availability (TOR §6.1 "Seasonal availability can hide or
     * flag products outside season"). Handles a season window that wraps
     * the calendar year (e.g. Nov -> Feb).
     */
    public function getIsInSeasonAttribute(): bool
    {
        if (! $this->is_seasonal || ! $this->season_start_month || ! $this->season_end_month) {
            return true;
        }

        $month = (int) now()->format('n');
        $start = $this->season_start_month;
        $end = $this->season_end_month;

        return $start <= $end
            ? $month >= $start && $month <= $end
            : $month >= $start || $month <= $end;
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_primary', true) ?? $this->images->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereColumn('available_quantity', '>', 'reserved_quantity');
    }

    /**
     * Simple typo-tolerant-enough search across name/description/category
     * (TOR §6.2). LIKE-based so it works identically on SQLite (dev) and
     * MySQL (production) without requiring a search engine in Phase 1.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like));
        });
    }
}
