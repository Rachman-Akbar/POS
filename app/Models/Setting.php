<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'group',
        'value',
        'label',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get a setting value by key, with a fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->where('is_active', true)->value('value') ?? $default;
    }
}
