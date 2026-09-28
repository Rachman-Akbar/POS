<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    public function remember(string $key, Closure $callback, int $seconds = 300): mixed
    {
        $version = Cache::rememberForever('catalog.version', fn (): int => 1);
        $cacheKey = "catalog.{$version}.{$key}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($this->shouldCache($value)) {
            Cache::put($cacheKey, $value, now()->addSeconds($seconds));
        }

        return $value;
    }

    public function invalidate(): void
    {
        Cache::forever('catalog.version', (int) Cache::get('catalog.version', 1) + 1);
    }

    private function shouldCache(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof Paginator) {
            return $value->count() > 0;
        }

        if ($value instanceof Collection || $value instanceof EloquentCollection) {
            return $value->isNotEmpty();
        }

        return true;
    }
}
