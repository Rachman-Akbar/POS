<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'publicIndex']);
Route::get('/categories/{identifier}', [CategoryController::class, 'publicShow']);
Route::get('/products', [ProductController::class, 'publicIndex']);
Route::get('/products/{identifier}', [ProductController::class, 'publicShow']);
Route::get('/media/products/{filename}', [MediaController::class, 'product'])
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('media.products');

Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('throttle:10,1');
Route::get('/orders/track', [OrderController::class, 'track'])->middleware('throttle:30,1');
Route::match(['put', 'patch'], '/orders/track/{orderNumber}', [OrderController::class, 'updateGuest'])
    ->middleware('throttle:15,1');
Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancelGuest'])
    ->middleware('throttle:10,1');

Route::prefix('admin')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');

    Route::middleware(['auth:sanctum', 'role:admin,seller'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', AdminDashboardController::class);

        Route::apiResource('categories', CategoryController::class);
        Route::post('/categories/{category}', [CategoryController::class, 'update']);
        Route::apiResource('products', ProductController::class);
        Route::post('/products/{product}', [ProductController::class, 'update']);
        Route::apiResource('orders', OrderController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::apiResource('users', UserController::class);
    });
});
