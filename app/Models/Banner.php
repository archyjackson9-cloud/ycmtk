<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 2 (TOR §6.8 homepage banner / category-spotlight CMS-lite).
 */
class Banner extends Model
{
    protected $fillable = [
        'title', 'image', 'video', 'link_url', 'position', 'sort_order', 'is_active',
        'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeLive(Builder $query, string $position): Builder
    {
        return $query->where('position', $position)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->orderBy('sort_order');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function videoUrl(): ?string
    {
        return $this->video ? asset('storage/'.$this->video) : null;
    }

    public function hasVideo(): bool
    {
        return filled($this->video);
    }
}
