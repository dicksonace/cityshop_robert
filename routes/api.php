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
use App\Http\Controllers\Api\V1\QrPaymentController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\UserLookupController;
use App\Http\Controllers\Api\V1\SellerFollowController;
use App\Http\Controllers\Api\V1\UserBlockController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Broadcasting\BroadcastController;
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
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/matches-for-recent-views', [ProductController::class, 'matchesForRecentViews']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::post('/products/{slug}/video-play', [ProductController::class, 'recordVideoPlay']);
    Route::get('/stores/{slug}', [StoreController::class, 'show']);
    Route::get('/livestreams', [\App\Http\Controllers\Api\V1\LivestreamController::class, 'index']);
    Route::get('/livestreams/{slug}', [\App\Http\Controllers\Api\V1\LivestreamController::class, 'show']);
    Route::post('/search/image', [ImageSearchController::class, 'store']);

    // Paystack in-app WebView return (no auth — app detects URL then calls verify)
    Route::get('/paystack/mobile-return', [CheckoutController::class, 'paystackMobileReturn']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::match(['get', 'post'], '/broadcasting/auth', [BroadcastController::class, 'authenticate']);

        Route::get('/realtime/config', function () {
            $key = config('broadcasting.connections.reverb.key');
            $host = (string) config('broadcasting.connections.reverb.options.host');
            if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', ''], true)) {
                $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();
            }

            return response()->json([
                'enabled' => filled($key) && config('broadcasting.default') === 'reverb',
                'key' => $key,
                'host' => $host,
                'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
                'auth_endpoint' => url('/api/v1/broadcasting/auth'),
                'ice_servers' => \App\Support\IceServers::forClient(),
            ]);
        });

        Route::get('/calls/ice-servers', fn () => response()->json([
            'ice_servers' => \App\Support\IceServers::forClient(),
            'has_relay' => \App\Support\IceServers::hasRelay(),
        ]));

        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::post('/livestreams/start', [\App\Http\Controllers\Api\V1\LivestreamController::class, 'start']);
        Route::post('/livestreams/heartbeat', [\App\Http\Controllers\Api\V1\LivestreamController::class, 'heartbeat']);
        Route::post('/livestreams/end', [\App\Http\Controllers\Api\V1\LivestreamController::class, 'end']);

        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
        Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('/profile/payment-pin', [\App\Http\Controllers\Api\PaymentPinController::class, 'store']);
        Route::put('/profile/payment-pin', [\App\Http\Controllers\Api\PaymentPinController::class, 'update']);
        Route::post('/profile/payment-pin/forgot', [\App\Http\Controllers\Api\PaymentPinController::class, 'forgot']);
        Route::post('/profile/payment-pin/reset', [\App\Http\Controllers\Api\PaymentPinController::class, 'reset']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);

        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
        Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy']);

        Route::get('/following', [SellerFollowController::class, 'following']);
        Route::get('/followers', [SellerFollowController::class, 'followers']);
        Route::get('/following/status', [SellerFollowController::class, 'status']);
        Route::post('/following/toggle', [SellerFollowController::class, 'toggle']);

        Route::get('/addresses', [AddressController::class, 'index']);
        Route::post('/addresses', [AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
        Route::post('/addresses/{address}/default', [AddressController::class, 'setDefault']);

        Route::get('/messages', [MessageController::class, 'index']);
        Route::get('/messages/forward-targets', [MessageController::class, 'forwardTargets']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::post('/messages/groups', [MessageController::class, 'storeGroup']);
        Route::get('/messages/{conversation}', [MessageController::class, 'show']);
        Route::delete('/messages/{conversation}', [MessageController::class, 'destroyConversation']);
        Route::post('/messages/{conversation}/members', [MessageController::class, 'addMembers']);
        Route::post('/messages/{conversation}/leave', [MessageController::class, 'leaveGroup']);
        Route::delete('/messages/{conversation}/members/{user}', [MessageController::class, 'removeMember']);
        Route::post('/messages/{conversation}/avatar', [MessageController::class, 'updateGroupAvatar']);
        Route::delete('/messages/{conversation}/avatar', [MessageController::class, 'destroyGroupAvatar']);
        Route::get('/messages/{conversation}/search', [MessageController::class, 'search']);
        Route::post('/messages/{conversation}/send', [MessageController::class, 'send']);
        Route::post('/messages/{conversation}/product', [MessageController::class, 'sendProduct']);
        Route::post('/messages/{conversation}/transfer', [MessageController::class, 'sendTransfer']);
        Route::post('/messages/{conversation}/image', [MessageController::class, 'uploadImage']);
        Route::post('/messages/{conversation}/video', [MessageController::class, 'uploadVideo']);
        Route::post('/messages/{conversation}/voice', [MessageController::class, 'uploadVoice']);
        Route::post('/messages/{conversation}/file', [MessageController::class, 'uploadFile']);
        Route::post('/messages/{conversation}/signal', [MessageController::class, 'signal']);
        Route::get('/messages/{conversation}/poll', [MessageController::class, 'poll']);
        Route::patch('/messages/{conversation}/messages/{message}', [MessageController::class, 'update']);
        Route::post('/messages/{conversation}/messages/{message}/react', [MessageController::class, 'react']);
        Route::delete('/messages/{conversation}/messages/{message}', [MessageController::class, 'destroy']);
        Route::post('/messages/{conversation}/messages/{message}/forward', [MessageController::class, 'forward']);

        Route::post('/sellers/report', [\App\Http\Controllers\Api\V1\SellerReportController::class, 'store']);

        Route::get('/users/lookup', [UserLookupController::class, 'lookup']);
        Route::get('/blocks', [UserBlockController::class, 'index']);
        Route::post('/blocks', [UserBlockController::class, 'store']);
        Route::delete('/blocks/{user}', [UserBlockController::class, 'destroy']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/counts', [NotificationController::class, 'counts']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

        Route::get('/checkout', [CheckoutController::class, 'preview']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
        Route::get('/checkout/direct-pay', [CheckoutController::class, 'directPay']);
        Route::post('/checkout/direct-pay/{sellerId}', [CheckoutController::class, 'submitDirectPay']);
        Route::post('/checkout/paystack/initialize', [CheckoutController::class, 'initializeDraftPaystack']);
        Route::post('/checkout/paystack/verify', [CheckoutController::class, 'verifyDraftPaystack']);
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
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
        Route::get('/wallet/transactions/by-reference/{reference}', [WalletController::class, 'transactionByReference']);
        Route::get('/wallet/withdrawals', [WalletController::class, 'withdrawals']);
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/wallet/manual-funding', [WalletController::class, 'manualFunding']);
        Route::post('/wallet/manual-top-up', [WalletController::class, 'manualTopUp']);
        Route::get('/wallet/manual-top-up/{topUp}', [WalletController::class, 'showManualTopUp']);
        Route::post('/wallet/manual-top-up/{topUp}/cancel', [WalletController::class, 'cancelManualTopUp']);
        Route::post('/wallet/paystack/initialize', [WalletController::class, 'initializePaystackTopUp']);
        Route::post('/wallet/paystack/verify', [WalletController::class, 'verifyPaystackTopUp']);
        Route::get('/wallet/qr/receive', [QrPaymentController::class, 'receive']);
        Route::post('/wallet/qr/resolve', [QrPaymentController::class, 'resolve']);
        Route::post('/wallet/qr/pay', [QrPaymentController::class, 'pay']);
    });
});
