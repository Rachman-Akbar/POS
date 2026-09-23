<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/kasir', 'app');
Route::view('/koki', 'app');
Route::view('/waiters', 'app');
Route::view('/{any}', 'app')->where('any', '.*');
