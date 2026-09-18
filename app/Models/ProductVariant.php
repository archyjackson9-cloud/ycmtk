<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 2 (TOR §9 ProductVariant). Not yet surfaced on the storefront.
 */
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'label', 'attributes', 'price_override', 'stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'price_override' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function price(): float
    {
        return (float) ($this->price_override ?? $this->product->selling_price);
    }
}
