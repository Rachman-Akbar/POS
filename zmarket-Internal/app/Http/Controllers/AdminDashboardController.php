<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $latestOrders = Order::query()
            ->with('items')
            ->latest('id')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => [
                'categories' => Category::query()->count(),
                'products' => Product::query()->count(),
                'users' => $request->user()?->role === 'admin' ? User::query()->count() : null,
                'published_products' => Product::query()
                    ->where('status', 'published')
                    ->where('is_active', true)
                    ->count(),
                'pending_orders' => Order::query()->where('status', 'pending')->count(),
                'orders' => Order::query()->count(),
                'revenue' => (float) Order::query()
                    ->where('status', 'completed')
                    ->sum('total_amount'),
                'latest_orders' => $latestOrders,
            ],
        ]);
    }
}
