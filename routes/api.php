<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout']);

// Public POS config (payment methods, tax rates, QRIS)
Route::get('/settings', [SettingsController::class, 'index']);

// Product catalog
Route::get('/products', [ProductController::class, 'index']);

// Waiter / order flow
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{order}', [OrderController::class, 'show']);
Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);

// Kitchen KDS flow (per-menu item status)
Route::get('/kitchen/items', [KitchenController::class, 'index']);
Route::patch('/kitchen/items/{item}/status', [KitchenController::class, 'updateItemStatus']);

// Cashier flow
Route::get('/payments/pending', [PaymentController::class, 'pending']);
Route::get('/payments/today', [PaymentController::class, 'today']);
Route::get('/payments/unpaid-orders', [PaymentController::class, 'unpaidOrders']);
Route::post('/payments/orders/{order}/settle', [PaymentController::class, 'settle']);

// Accounting
Route::get('/accounting/journal', [AccountingController::class, 'journal']);
Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts']);
