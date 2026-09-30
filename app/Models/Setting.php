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
        'cashier_show_sku' => true,
        'cashier_enable_table' => true,
        'cashier_enable_customer' => true,
        'cashier_enable_ppn' => true,
        'cashier_enable_prepay' => false,
    ];

    /**
     * Theme modes and accent colors selectable from the Admin panel.
     *
     * @var array<int, string>
     */
    public const THEME_MODES = ['light', 'dark', 'system'];

    /**
     * @var array<int, string>
     */
    public const THEME_ACCENTS = ['system', 'orange', 'blue', 'emerald', 'purple', 'rose', 'teal', 'pink', 'slate'];

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

        return array_map(fn (int $n): string => (string) $n, range(1, 20));
    }

    /**
     * The display order of product categories set from the Admin panel.
     *
     * @return array<int, string>
     */
    public static function categoryOrder(): array
    {
        $raw = static::get('pos.category_order', '');
        $decoded = $raw ? json_decode((string) $raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), fn (string $name): bool => $name !== ''));
    }

    /**
     * Persist the display order of product categories.
     *
     * @param  array<int, string>  $order
     */
    public static function saveCategoryOrder(array $order): void
    {
        static::query()->updateOrCreate(
            ['key' => 'pos.category_order'],
            [
                'group' => 'pos',
                'label' => 'Urutan Kategori',
                'value' => json_encode(array_values($order), JSON_UNESCAPED_UNICODE),
                'is_active' => true,
            ]
        );
    }

    /**
     * The appearance used when nothing has been configured yet.
     *
     * @return array{mode: string, accent: string}
     */
    public static function defaultAppearance(): array
    {
        return ['mode' => 'light', 'accent' => 'system'];
    }

    /**
     * The appearance (theme mode + accent color) applied across the app.
     *
     * @return array{mode: string, accent: string}
     */
    public static function appearance(): array
    {
        $defaults = self::defaultAppearance();
        $mode = static::get('app.theme_mode', $defaults['mode']);
        $accent = static::get('app.theme_accent', $defaults['accent']);

        return [
            'mode' => in_array($mode, self::THEME_MODES, true) ? $mode : $defaults['mode'],
            'accent' => in_array($accent, self::THEME_ACCENTS, true) ? $accent : $defaults['accent'],
        ];
    }
}
