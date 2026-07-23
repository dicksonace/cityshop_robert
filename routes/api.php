<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CityShop Mobile API (v1)
|--------------------------------------------------------------------------
|
| Token auth via Laravel Sanctum. Send:
|   Authorization: Bearer {token}
|   Accept: application/json
|
| Same business services as the Inertia web app — separate JSON controllers.
|
*/

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'ok' => true,
        'app' => 'CityShop',
        'version' => 'v1',
    ]));

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

        Route::get('/checkout', [CheckoutController::class, 'preview']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/checkouts/{checkout}', [CheckoutController::class, 'show']);
        Route::post('/checkouts/{checkout}/pay/wallet', [CheckoutController::class, 'payWithWallet']);
        Route::post('/checkouts/{checkout}/pay/initialize', [CheckoutController::class, 'initializePaystack']);
        Route::post('/orders/{order}/direct-payment', [CheckoutController::class, 'submitDirectPayment']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/items/{orderItem}/confirm-delivery', [OrderController::class, 'confirmDelivery']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/manual-funding', [WalletController::class, 'manualFunding']);
        Route::post('/wallet/manual-top-up', [WalletController::class, 'manualTopUp']);
    });
});
