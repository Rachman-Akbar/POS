<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * Cashier UI flags managed from the Admin panel, with their defaults.
     *
     * @var array<string, bool>
     */
    private const CASHIER_FLAGS = [
        'cashier_show_favorites' => true,
        'cashier_show_stock' => true,
        'cashier_enable_table' => true,
        'cashier_enable_ppn' => true,
        'cashier_enable_prepay' => false,
    ];

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

    /**
     * All cashier UI flags resolved to booleans.
     *
     * @return array<string, bool>
     */
    public static function cashierFlags(): array
    {
        $flags = [];
        foreach (self::CASHIER_FLAGS as $key => $default) {
            $value = static::get('pos.'.$key, $default);
            $flags[$key] = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $flags;
    }

    /**
     * The default room/table numbers available at the cashier.
     *
     * @return array<int, string>
     */
    public static function tableNumbers(): array
    {
        $raw = static::get('pos.table_numbers', '');

        if ($raw) {
            return array_values(array_filter(array_map('trim', explode(',', (string) $raw)), fn ($t) => $t !== ''));
        }

        return array_map(fn (int $n) => (string) $n, range(1, 20));
    }
}
