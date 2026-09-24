<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var Collection<int, Product> $products */
        $products = Product::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * Toggle a product's favorite flag (managed from the Admin panel).
     */
    public function toggleFavorite(Product $product): JsonResponse
    {
        $product->update(['is_favorite' => ! $product->is_favorite]);

        return response()->json(['data' => $product->fresh()]);
    }
}
