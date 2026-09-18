<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => Cache::forget('setting:'.$setting->key));
        static::deleted(fn (Setting $setting) => Cache::forget('setting:'.$setting->key));
    }
}
