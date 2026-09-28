<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'seller'], true);
    }

    protected function prepareForValidation(): void
    {
        $primaryCategoryId = $this->input('primary_category_id', $this->input('category_id'));
        $categoryIds = $this->decodeArray($this->input('category_ids', $this->input('categories', [])));
        $existingImages = $this->decodeArray($this->input('existing_images', []));

        $categoryIds = collect(is_array($categoryIds) ? $categoryIds : [])
            ->map(fn ($category): mixed => is_array($category) ? ($category['id'] ?? null) : $category)
            ->filter(fn ($category): bool => is_numeric($category))
            ->map(fn ($category): int => (int) $category)
            ->when(filled($primaryCategoryId), fn ($items) => $items->prepend((int) $primaryCategoryId))
            ->unique()
            ->values()
            ->all();

        $existingImages = collect(is_array($existingImages) ? $existingImages : [])
            ->map(fn ($image): mixed => is_array($image) ? ($image['path'] ?? $image['url'] ?? null) : $image)
            ->filter(fn ($image): bool => is_string($image) && trim($image) !== '')
            ->values()
            ->all();

        $merge = [
            'primary_category_id' => filled($primaryCategoryId) ? (int) $primaryCategoryId : null,
            'category_id' => filled($primaryCategoryId) ? (int) $primaryCategoryId : null,
            'category_ids' => $categoryIds,
        ];

        if ($this->has('existing_images')) {
            $merge['existing_images'] = $existingImages;
        }

        if ($this->filled('slug')) {
            $merge['slug'] = Str::slug((string) $this->input('slug'));
        } elseif ($this->filled('name')) {
            $merge['slug'] = Str::slug((string) $this->input('name'));
        }

        foreach (['track_stock', 'is_featured', 'is_active'] as $key) {
            if ($this->has($key)) {
                $merge[$key] = $this->toBoolean($this->input($key));
            }
        }

        if ($this->filled('sku')) {
            $merge['sku'] = Str::upper(trim((string) $this->input('sku')));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_ids' => ['required', 'array', 'min:1', 'max:20'],
            'category_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($productId)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'type' => ['required', Rule::in(['product', 'service'])],
            'description' => ['nullable', 'string', 'max:20000'],
            'brand' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'track_stock' => ['required', 'boolean'],
            'stock' => ['nullable', 'required_if:track_stock,true', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'existing_images' => ['nullable', 'array', 'max:12'],
            'existing_images.*' => ['string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:12'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $product = $this->route('product');
            $existingImages = $this->has('existing_images')
                ? collect($this->input('existing_images', []))
                : collect($product instanceof Product ? $product->images()->pluck('url')->all() : []);
            $newImageCount = count($this->file('images', []));

            if ($existingImages->count() + $newImageCount > 12) {
                $validator->errors()->add('images', 'Total gambar maksimal 12.');
            }

            if (! collect($this->input('category_ids', []))->contains((int) $this->input('primary_category_id'))) {
                $validator->errors()->add('category_ids', 'Kategori utama harus termasuk dalam daftar kategori produk.');
            }

        }];
    }

    private function decodeArray(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
