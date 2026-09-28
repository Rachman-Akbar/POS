<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductMediaService
{
    public function prepare(array $existingPaths, array $keptPaths, array $files): array
    {
        $existing = collect($existingPaths)
            ->map(fn ($path) => $this->normalizePath($path))
            ->filter()
            ->unique()
            ->values();

        $kept = collect($keptPaths)
            ->map(fn ($path) => $this->normalizePath($path))
            ->filter(fn ($path): bool => $existing->contains($path))
            ->unique()
            ->values();

        $stored = collect($files)
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): string => $file->store('products', 'public'))
            ->filter()
            ->values();

        return [
            'paths' => $kept->concat($stored)->unique()->values()->all(),
            'new_paths' => $stored->all(),
            'removed_paths' => $existing->diff($kept)->values()->all(),
        ];
    }

    public function deleteMany(array $paths): void
    {
        collect($paths)
            ->map(fn ($path) => $this->normalizePath($path))
            ->filter(fn ($path): bool => filled($path) && ! $this->isRemoteUrl($path))
            ->each(fn (string $path): bool => Storage::disk('public')->delete($path));
    }

    private function normalizePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path['path'] ?? $path['url'] ?? null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            return null;
        }

        if ($this->isRemoteUrl($path)) {
            $urlPath = parse_url($path, PHP_URL_PATH);

            if (is_string($urlPath) && preg_match('#/(?:api/)?media/products/([^/]+)$#', $urlPath, $matches)) {
                return 'products/'.basename($matches[1]);
            }

            if (is_string($urlPath) && preg_match('#/storage/products/([^/]+)$#', $urlPath, $matches)) {
                return 'products/'.basename($matches[1]);
            }

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

    private function isRemoteUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }
}
