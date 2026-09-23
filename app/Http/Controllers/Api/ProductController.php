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
}
