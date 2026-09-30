<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TransactionCorrectionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

/*
|--------------------------------------------------------------------------
| Rute Terlindungi
|--------------------------------------------------------------------------
| Seluruh API memakai auth (Sanctum) + permission. Tidak ada lagi endpoint
| publik selain login: katalog, transaksi, dapur, dan pelayan semuanya
| menulis ke database, jadi membiarkannya terbuka berarti siapa pun yang
| tahu URL bisa mengubah data tanpa jejak. Permission tetap diperiksa di backend,
| menyembunyikan menu di frontend bukan jaminan keamanan.
*/
Route::middleware(['auth:sanctum', 'user.active'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Pengaturan yang dibaca kasir (tampilan, nomor meja, enable PPN, dll).
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('permission:settings.view');

    // Katalog produk
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:product.view');

    // Alur pesanan (kasir, pelayan, dapur)
    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:transaction.view');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:transaction.create');
    Route::post('/orders/draft', [OrderController::class, 'storeDraft'])->middleware('permission:transaction.create');
    Route::get('/orders/transactions', [OrderController::class, 'transactions'])->middleware('permission:transaction.view');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->middleware('permission:transaction.update');
    Route::post('/orders/{order}/finalize', [OrderController::class, 'finalizeDraft'])->middleware('permission:transaction.update');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('permission:transaction.update');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:transaction.view');
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->middleware('permission:transaction.update');

    // Koreksi transaksi oleh admin: memperbaiki isi pesanan, pembatalan total,
    // dan retur pembayaran. Kasir sengaja tidak diberi permission ini di role
    // bawaannya.
    Route::put('/orders/{order}/corrections', [TransactionCorrectionController::class, 'correct'])->middleware('permission:transaction.correct');
    Route::post('/orders/{order}/void', [TransactionCorrectionController::class, 'void'])->middleware('permission:transaction.void');
    Route::post('/orders/{order}/refunds/{receipt}', [TransactionCorrectionController::class, 'refund'])->middleware('permission:transaction.refund');

    // Pelanggan saat transaksi
    Route::get('/customers', [CustomerController::class, 'search'])->middleware('permission:customer.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customer.create');

    // Dapur (KDS)
    Route::get('/kitchen/items', [KitchenController::class, 'index'])->middleware('permission:kitchen.view');
    Route::patch('/kitchen/items/{item}/status', [KitchenController::class, 'updateItemStatus'])->middleware('permission:kitchen.update');

    // Kasir & pembayaran
    Route::get('/payments/pending', [PaymentController::class, 'pending'])->middleware('permission:pos.view');
    Route::get('/payments/invoices', [PaymentController::class, 'invoices'])->middleware('permission:pos.view');
    Route::get('/payments/today', [PaymentController::class, 'today'])->middleware('permission:pos.view');
    Route::get('/payments/unpaid-orders', [PaymentController::class, 'unpaidOrders'])->middleware('permission:pos.view');
    Route::post('/payments/orders/{order}/settle', [PaymentController::class, 'settle'])->middleware('permission:pos.sell');

    // Akuntansi
    Route::get('/accounting/journal', [AccountingController::class, 'journal'])->middleware('permission:accounting.view');
    Route::get('/accounting/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])->middleware('permission:accounting.view');

    // Pengaturan admin (tampilan kasir & tema aplikasi)
    Route::get('/admin/settings', [AdminController::class, 'index'])->middleware('permission:settings.view');
    Route::put('/admin/settings', [AdminController::class, 'update'])->middleware('permission:settings.update');

    // Kategori
    Route::get('/categories', [AdminController::class, 'categories'])->middleware('permission:category.view');
    Route::put('/categories/order', [AdminController::class, 'updateCategoryOrder'])->middleware('permission:category.reorder');

    // Master data: kategori, produk, pelanggan
    Route::get('/admin/categories', [CategoryController::class, 'index'])->middleware('permission:category.view');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->middleware('permission:category.create');
    Route::put('/admin/categories/order', [CategoryController::class, 'reorder'])->middleware('permission:category.reorder');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:category.update');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:category.delete');

    Route::get('/admin/products', [AdminProductController::class, 'index'])->middleware('permission:product.view');
    Route::post('/admin/products', [AdminProductController::class, 'store'])->middleware('permission:product.create');
    Route::put('/admin/products/{product}', [AdminProductController::class, 'update'])->middleware('permission:product.update');
    Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroy'])->middleware('permission:product.delete');
    Route::patch('/products/{product}/favorite', [ProductController::class, 'toggleFavorite'])->middleware('permission:product.favorite');

    Route::get('/admin/customers', [CustomerController::class, 'index'])->middleware('permission:customer.view');
    Route::post('/admin/customers', [CustomerController::class, 'store'])->middleware('permission:customer.create');
    Route::put('/admin/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customer.update');
    Route::delete('/admin/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customer.delete');

    // Kas & Bank
    Route::get('/cash-bank-accounts', [AdminController::class, 'cashBankAccounts'])->middleware('permission:cash.view');
    Route::post('/cash-bank-accounts', [AdminController::class, 'storeCashBankAccount'])->middleware('permission:cash.create');
    Route::get('/cash-bank-accounts/{cashBankAccount}/mutations', [AdminController::class, 'accountMutations'])->middleware('permission:cash.view');
    Route::put('/cash-bank-accounts/{cashBankAccount}', [AdminController::class, 'updateCashBankAccount'])->middleware('permission:cash.update');
    Route::delete('/cash-bank-accounts/{cashBankAccount}', [AdminController::class, 'destroyCashBankAccount'])->middleware('permission:cash.delete');

    // Metode Pembayaran
    Route::get('/payment-methods', [AdminController::class, 'paymentMethods'])->middleware('permission:payment_method.view');
    Route::post('/payment-methods', [AdminController::class, 'storePaymentMethod'])->middleware('permission:payment_method.create');
    Route::put('/payment-methods/{paymentMethod}', [AdminController::class, 'updatePaymentMethod'])->middleware('permission:payment_method.update');
    Route::delete('/payment-methods/{paymentMethod}', [AdminController::class, 'destroyPaymentMethod'])->middleware('permission:payment_method.delete');

    // Kelompok hak akses
    Route::get('/admin/permissions', [PermissionController::class, 'index'])->middleware('permission:role.view');
    Route::get('/admin/roles', [RoleController::class, 'index'])->middleware('permission:role.view');
    Route::post('/admin/roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::put('/admin/roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('/admin/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    // User
    Route::get('/admin/users', [UserController::class, 'index'])->middleware('permission:user.view');
    Route::post('/admin/users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::post('/admin/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:user.update');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
});
