<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Master kategori: baris tabel `categories` digabung dengan kategori yang
     * dipakai produk tanpa terdaftar, supaya katalog kasir tetap utuh.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->rows()]);
    }

    /**
     * Daftarkan kategori baru (boleh sudah dipakai produk, mis. hasil impor lama).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        Category::register($data['name']);
        $this->syncOrder();

        $category = Category::query()->where('name', $data['name'])->firstOrFail();

        return response()->json(['data' => $category], Response::HTTP_CREATED);
    }

    /**
     * Ubah nama/status kategori. Mengganti nama ikut memperbarui produk.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request, $category);
        $previous = $category->name;

        DB::transaction(function () use ($category, $data, $previous): void {
            $category->update($data);

            if ($previous !== $data['name']) {
                Product::query()->where('category', $previous)->update(['category' => $data['name']]);
            }
        });

        $this->syncOrder();

        return response()->json(['data' => $category->fresh()]);
    }

    /**
     * Hapus kategori yang tidak lagi dipakai produk.
     */
    public function destroy(Category $category): Response|JsonResponse
    {
        $total = $category->products()->count();

        if ($total > 0) {
            return response()->json([
                'message' => "Kategori masih dipakai {$total} produk. Pindahkan produk terlebih dahulu.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category->delete();
        $this->syncOrder();

        return response()->noContent();
    }

    /**
     * Simpan urutan tampil kategori.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'distinct', 'max:100'],
        ]);

        $known = array_column($this->rows(), 'name');
        $order = array_values($validated['order']);

        if (array_diff($order, $known) || array_diff($known, $order)) {
            return response()->json([
                'message' => 'Urutan kategori harus memuat seluruh kategori yang ada.',
                'errors' => ['order' => ['Urutan kategori tidak lengkap atau tidak dikenal.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($order as $position => $name) {
            Category::query()->where('name', $name)->update(['sort_order' => $position]);
        }

        Setting::saveCategoryOrder($order);

        return response()->json(['data' => $this->rows()]);
    }

    /**
     * Daftar kategori urut tampil, lengkap dengan jumlah produk.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $counts = Product::query()
            ->selectRaw("COALESCE(category, 'Lainnya') as name, COUNT(*) as total")
            ->groupByRaw("COALESCE(category, 'Lainnya')")
            ->pluck('total', 'name')
            ->all();

        $registered = Category::query()->get()->keyBy('name');
        $order = Setting::categoryOrder();
        $position = fn (string $name): int => ($index = array_search($name, $order, true)) === false ? PHP_INT_MAX : $index;

        $names = collect(array_keys($counts))
            ->merge($registered->keys())
            ->unique()
            ->sort(fn (string $a, string $b): int => ($position($a) <=> $position($b)) ?: strnatcasecmp($a, $b))
            ->values();

        return $names->map(function (string $name) use ($counts, $registered): array {
            $record = $registered->get($name);

            return [
                'id' => $record?->id,
                'name' => $name,
                'total' => (int) ($counts[$name] ?? 0),
                'is_active' => $record?->is_active ?? true,
                'is_registered' => $record !== null,
                'sort_order' => $record?->sort_order ?? PHP_INT_MAX,
            ];
        })->all();
    }

    /**
     * @return array{name: string, is_active: bool}
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'name' => filled($request->input('name')) ? trim((string) $request->input('name')) : null,
        ]);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($category?->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($category?->is_active ?? true),
        ];
    }

    /**
     * Jaga cache urutan katalog tetap sinkron dengan master kategori.
     */
    private function syncOrder(): void
    {
        Setting::saveCategoryOrder(array_column($this->rows(), 'name'));
    }
}
