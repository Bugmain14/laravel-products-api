<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products/trashed', [ProductController::class, 'trashed']);
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->withTrashed();
    Route::delete('/products/{product}/force', [ProductController::class, 'forceDestroy'])->withTrashed();

    Route::get('/products/{product}/prices', [ProductPriceController::class, 'index']);
    Route::post('/products/{product}/prices', [ProductPriceController::class, 'store']);

    Route::apiResource('currencies', CurrencyController::class);
});
