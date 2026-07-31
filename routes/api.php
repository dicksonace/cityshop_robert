<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ImageSearchController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WishlistController;
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
    Route::get('/stores/{slug}', [StoreController::class, 'show']);
    Route::post('/search/image', [ImageSearchController::class, 'store']);

    // Paystack in-app WebView return (no auth — app detects URL then calls verify)
    Route::get('/paystack/mobile-return', [CheckoutController::class, 'paystackMobileReturn']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
        Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault']);

        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::get('/messages/{conversation}', [MessageController::class, 'show']);
        Route::post('/messages/{conversation}/send', [MessageController::class, 'send']);
        Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll']);
        Route::delete('/messages/{conversation}/messages/{message}', [MessageController::class, 'destroy']);

        Route::get('/checkout', [CheckoutController::class, 'preview']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/checkouts/{checkout}', [CheckoutController::class, 'show']);
        Route::post('/checkouts/{checkout}/pay/wallet', [CheckoutController::class, 'payWithWallet']);
        Route::post('/checkouts/{checkout}/pay/initialize', [CheckoutController::class, 'initializePaystack']);
        Route::post('/checkouts/{checkout}/pay/verify', [CheckoutController::class, 'verifyPaystack']);
        Route::post('/orders/{order}/direct-payment', [CheckoutController::class, 'submitDirectPayment']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/orders/{order}/items/{orderItem}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
        Route::post('/orders/{order}/disputes', [DisputeController::class, 'store']);
        Route::post('/disputes/{dispute}/cancel', [DisputeController::class, 'cancel']);
        Route::post('/orders/{order}/reviews', [ReviewController::class, 'store']);

        Route::get('/wallet', [WalletController::class, 'show']);
        Route::get('/wallet/manual-funding', [WalletController::class, 'manualFunding']);
        Route::post('/wallet/manual-top-up', [WalletController::class, 'manualTopUp']);
        Route::post('/wallet/paystack/initialize', [WalletController::class, 'initializePaystackTopUp']);
        Route::post('/wallet/paystack/verify', [WalletController::class, 'verifyPaystackTopUp']);
    });
});
