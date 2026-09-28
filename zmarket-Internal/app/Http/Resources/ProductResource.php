<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->relationLoaded('images')
            ? $this->images->map(fn ($image): array => [
                'id' => $image->id,
                'path' => $this->normalizeImagePath($image->url),
                'url' => $this->imageUrl($image->url),
                'alt_text' => $image->alt_text,
                'is_primary' => (bool) $image->is_primary,
                'sort_order' => (int) $image->sort_order,
            ])->filter(fn (array $image): bool => filled($image['url']))->values()
            : collect();
        $firstImage = $images->first();

        return [
            'id' => $this->id,
            'category_id' => $this->primary_category_id,
            'primary_category_id' => $this->primary_category_id,
            'category' => $this->relationLoaded('primaryCategory') && $this->primaryCategory
                ? new CategoryResource($this->primaryCategory)
                : null,
            'categories' => $this->relationLoaded('categories')
                ? CategoryResource::collection($this->categories)
                : [],
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'type' => $this->type,
            'description' => $this->description,
            'brand' => $this->brand,
            'images' => $images->all(),
            'image_urls' => $images->pluck('url')->filter()->values()->all(),
            'thumbnail' => $firstImage['url'] ?? $this->imageUrl($this->thumbnail),
            'price' => (float) $this->price,
            'track_stock' => (bool) $this->track_stock,
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'available' => $this->isAvailable(),
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function normalizeImagePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return filled($path) ? $path : null;
    }

    private function imageUrl(?string $path): ?string
    {
        $path = $this->normalizeImagePath($path);

        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $filename = basename($path);

        return $filename === ''
            ? null
            : route('media.products', ['filename' => $filename]);
    }
}
