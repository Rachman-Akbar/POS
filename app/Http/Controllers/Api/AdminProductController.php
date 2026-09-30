<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Master produk: semua produk (aktif maupun nonaktif) dengan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $products = Product::query()
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->orderBy('category')
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validated($request));
        Category::register($product->category);

        $this->audit->log($request, 'create', 'product', $product, "Menambah produk {$product->name}.");

        return response()->json(['data' => $product], Response::HTTP_CREATED);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $old = $product->only(['name', 'sku', 'price', 'stock', 'is_active', 'category']);

        $product->update($this->validated($request, $product));
        Category::register($product->category);

        $this->audit->log(
            $request,
            'update',
            'product',
            $product,
            "Memperbarui produk {$product->name}.",
            $old,
            $product->only(['name', 'sku', 'price', 'stock', 'is_active', 'category']),
        );

        return response()->json(['data' => $product->fresh()]);
    }

    /**
     * Produk yang sudah pernah masuk transaksi tidak boleh dihapus.
     */
    public function destroy(Request $request, Product $product): Response|JsonResponse
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Produk sudah dipakai pada transaksi. Nonaktifkan saja agar tidak tampil di kasir.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $product->name;
        $product->delete();

        $this->audit->log($request, 'delete', 'product', null, "Menghapus produk {$name}.");

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $this->prepareForValidation($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock' => ['required', 'integer', 'min:0', 'max:999999'],
            'category' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'string', 'max:500'],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'sku.unique' => 'SKU sudah dipakai produk lain.',
        ]);

        $data['category'] = $data['category'] ?? null;
        $data['image'] = $data['image'] ?? null;
        $data['description'] = $data['description'] ?? null;
        $data['cost_price'] = (float) ($data['cost_price'] ?? 0);
        $data['is_favorite'] = array_key_exists('is_favorite', $data) ? (bool) $data['is_favorite'] : (bool) ($product?->is_favorite ?? false);
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($product?->is_active ?? true);

        return $data;
    }

    /**
     * Normalkan nilai sebelum validasi agar pengecekan unik berjalan benar.
     */
    private function prepareForValidation(Request $request): void
    {
        $request->merge([
            'name' => filled($request->input('name')) ? trim((string) $request->input('name')) : null,
            'sku' => filled($request->input('sku')) ? strtoupper(trim((string) $request->input('sku'))) : null,
            'category' => filled($request->input('category')) ? trim((string) $request->input('category')) : null,
            'image' => filled($request->input('image')) ? trim((string) $request->input('image')) : null,
            'description' => filled($request->input('description')) ? trim((string) $request->input('description')) : null,
        ]);
    }
}
