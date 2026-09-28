<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogCache;
use App\Services\ProductMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogCache $catalogCache,
        private readonly ProductMediaService $media
    ) {}

    public function publicIndex(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:170'],
            'type' => ['nullable', 'in:product,service'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,name_asc,name_desc,price_asc,price_desc'],
            'paginate' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Product::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->with($this->relations())
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $search = trim((string) $validated['search']);
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('categories', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($validated['category'] ?? null), function ($query) use ($validated): void {
                $category = (string) $validated['category'];
                $query->whereHas('categories', function ($query) use ($category): void {
                    $query->where('slug', $category);
                    if (is_numeric($category)) {
                        $query->orWhereKey((int) $category);
                    }
                });
            })
            ->when(filled($validated['type'] ?? null), fn ($query) => $query->where('type', $validated['type']))
            ->when((bool) ($validated['featured'] ?? false), fn ($query) => $query->where('is_featured', true));

        $this->applySort($query, $validated['sort'] ?? 'newest');

        if ((bool) ($validated['paginate'] ?? false)) {
            return ProductResource::collection(
                $query->paginate($validated['per_page'] ?? 24)->withQueryString()
            );
        }

        return ProductResource::collection($query->get());
    }

    public function publicShow(string $identifier): ProductResource
    {
        $product = Product::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);
                if (is_numeric($identifier)) {
                    $query->orWhereKey((int) $identifier);
                }
            })
            ->with($this->relations())
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureProductAccess($request->user());

        $products = Product::query()
            ->with($this->relations())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhereHas('categories', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $prepared = $this->media->prepare([], [], $request->file('images', []));

        try {
            $product = DB::transaction(function () use ($request, $prepared): Product {
                $data = $request->validated();
                $product = Product::query()->create($this->productPayload($data, $prepared['paths']));
                $this->syncCategories($product, $data['category_ids']);
                $this->syncImages($product, $prepared['paths']);

                return $product;
            }, 3);
        } catch (Throwable $exception) {
            $this->media->deleteMany($prepared['new_paths']);
            throw $exception;
        }

        $this->catalogCache->invalidate();

        return (new ProductResource($this->load($product)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($this->load($product));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $existingPaths = $product->images()->pluck('url')->all();
        $keptPaths = $request->has('existing_images')
            ? $request->validated('existing_images', [])
            : $existingPaths;
        $prepared = $this->media->prepare($existingPaths, $keptPaths, $request->file('images', []));

        try {
            DB::transaction(function () use ($request, $product, $prepared): void {
                $data = $request->validated();
                $product->update($this->productPayload($data, $prepared['paths'], $product));
                $this->syncCategories($product, $data['category_ids']);
                $this->syncImages($product, $prepared['paths']);
            }, 3);
        } catch (Throwable $exception) {
            $this->media->deleteMany($prepared['new_paths']);
            throw $exception;
        }

        $this->media->deleteMany($prepared['removed_paths']);
        $this->catalogCache->invalidate();

        return new ProductResource($this->load($product->fresh()));
    }

    public function destroy(Product $product): JsonResponse
    {
        $paths = $product->images()->pluck('url')->all();
        $product->delete();
        $this->media->deleteMany($paths);
        $this->catalogCache->invalidate();

        return response()->json(['message' => 'Produk berhasil dihapus.']);
    }

    private function productPayload(array $data, array $paths, ?Product $product = null): array
    {
        return [
            'primary_category_id' => (int) $data['primary_category_id'],
            'name' => trim((string) $data['name']),
            'slug' => $this->uniqueSlug($data['slug'] ?? null, $data['name'], $product?->id),
            'sku' => $this->uniqueSku($data['sku'] ?? null, $data['name'], $product?->id),
            'type' => $data['type'],
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'brand' => filled($data['brand'] ?? null) ? trim((string) $data['brand']) : null,
            'thumbnail' => $paths[0] ?? null,
            'price' => (float) $data['price'],
            'track_stock' => (bool) $data['track_stock'],
            'stock' => (bool) $data['track_stock'] ? (int) ($data['stock'] ?? 0) : null,
            'status' => $data['status'],
            'is_featured' => (bool) $data['is_featured'],
            'is_active' => (bool) $data['is_active'],
        ];
    }

    private function syncCategories(Product $product, array $categoryIds): void
    {
        $primaryId = (int) $product->primary_category_id;
        $sync = collect($categoryIds)
            ->unique()
            ->mapWithKeys(fn ($id): array => [(int) $id => ['is_primary' => (int) $id === $primaryId]])
            ->all();

        $product->categories()->sync($sync);
    }

    private function syncImages(Product $product, array $paths): void
    {
        $product->images()->delete();

        foreach (array_values($paths) as $index => $path) {
            $product->images()->create([
                'url' => $path,
                'alt_text' => $product->name,
                'is_primary' => $index === 0,
                'sort_order' => $index,
            ]);
        }
    }

    private function uniqueSlug(?string $requested, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(filled($requested) ? $requested : $name) ?: 'produk';
        $slug = $base;
        $counter = 2;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueSku(?string $requested, string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug(filled($requested) ? $requested : $name, '-')) ?: 'SKU';
        $base = Str::limit($base, 100, '');
        $sku = $base;
        $counter = 2;

        while (Product::query()->where('sku', $sku)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $suffix = '-'.$counter;
            $sku = Str::limit($base, 100 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $sku;
    }

    private function load(Product $product): Product
    {
        return $product->load($this->relations());
    }

    private function relations(): array
    {
        return ['primaryCategory', 'categories', 'images'];
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            default => $query->latest('id'),
        };
    }

    private function ensureProductAccess(?User $user): void
    {
        abort_unless($user && in_array($user->role, ['admin', 'seller'], true), 403);
    }
}
