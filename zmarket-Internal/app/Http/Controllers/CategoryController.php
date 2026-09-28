<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CategoryController extends Controller
{
    public function publicIndex(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => $this->publicPayload($category))
            ->values();

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function publicShow(string $identifier): JsonResponse
    {
        $category = Category::query()
            ->where('is_active', true)
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (is_numeric($identifier)) {
                    $query->orWhereKey((int) $identifier);
                }
            })
            ->firstOrFail();

        return response()->json([
            'data' => $this->publicPayload($category),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query()
            ->withCount('products')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->has('is_active'),
                fn ($query) => $query->where(
                    'is_active',
                    $request->boolean('is_active')
                )
            )
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('paginate')) {
            $categories = $query
                ->paginate(
                    min(
                        100,
                        max(1, $request->integer('per_page', 20))
                    )
                )
                ->withQueryString();

            return CategoryResource::collection($categories);
        }

        return CategoryResource::collection($query->get());
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $newImage = $request->file('image')?->store(
            'categories',
            'public'
        );

        try {
            $category = Category::query()->create([
                'name' => trim((string) $data['name']),
                'slug' => $this->uniqueSlug(
                    $data['slug'] ?? null,
                    $data['name']
                ),
                'description' => filled($data['description'] ?? null)
                    ? trim((string) $data['description'])
                    : null,
                'image' => $newImage,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : true,
                'sort_order' => array_key_exists('sort_order', $data)
                    ? (int) $data['sort_order']
                    : 0,
            ]);
        } catch (Throwable $exception) {
            $this->deleteImage($newImage);

            throw $exception;
        }

        return (new CategoryResource(
            $category->loadCount('products')
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource(
            $category->loadCount('products')
        );
    }

    public function update(
        CategoryRequest $request,
        Category $category
    ): CategoryResource {
        $data = $request->validated();

        $oldImage = $category->image;
        $newImage = $request->file('image')?->store(
            'categories',
            'public'
        );

        try {
            $category->update([
                'name' => trim((string) $data['name']),
                'slug' => $this->uniqueSlug(
                    $data['slug'] ?? null,
                    $data['name'],
                    $category->id
                ),
                'description' => filled($data['description'] ?? null)
                    ? trim((string) $data['description'])
                    : null,
                'image' => $newImage ?: $oldImage,
                'is_active' => array_key_exists('is_active', $data)
                    ? (bool) $data['is_active']
                    : (bool) $category->is_active,
                'sort_order' => array_key_exists('sort_order', $data)
                    ? (int) $data['sort_order']
                    : (int) $category->sort_order,
            ]);
        } catch (Throwable $exception) {
            $this->deleteImage($newImage);

            throw $exception;
        }

        if ($newImage && $newImage !== $oldImage) {
            $this->deleteImage($oldImage);
        }

        return new CategoryResource(
            $category->fresh()->loadCount('products')
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        abort_if(
            $category->products()->exists() || $category->primaryProducts()->exists(),
            422,
            'Kategori masih digunakan produk dan tidak dapat dihapus.'
        );

        $image = $category->image;

        $category->delete();

        $this->deleteImage($image);

        return response()->json([
            'message' => 'Kategori berhasil dihapus.',
        ]);
    }

    private function publicPayload(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $this->imageUrl($category->image),
            'is_active' => (bool) $category->is_active,
            'sort_order' => (int) $category->sort_order,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
    }

    private function uniqueSlug(
        ?string $requested,
        string $name,
        ?int $ignoreId = null
    ): string {
        $source = filled($requested)
            ? $requested
            : $name;

        $base = Str::slug((string) $source) ?: 'kategori';
        $slug = $base;
        $counter = 2;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId !== null,
                    fn ($query) => $query->whereKeyNot($ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return $path;
        }

        return url('/storage/'.ltrim($path, '/'));
    }

    private function deleteImage(?string $path): void
    {
        if (
            ! $path
            || str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
        ) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
