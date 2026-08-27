<?php

use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ImageSearchController;
use App\Http\Controllers\Api\V1\KycController;
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
use App\Http\Controllers\Api\V1\ChinaTransferController;
use App\Http\Controllers\Api\V1\SellRmbController;
use App\Http\Controllers\Api\V1\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\V1\Admin\BuyerController as AdminBuyerController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Api\V1\Admin\ChinaTransferController as AdminChinaTransferController;
use App\Http\Controllers\Api\V1\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Api\V1\Admin\KycController as AdminKycController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PendingFundController as AdminPendingFundController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\SellRmbController as AdminSellRmbController;
use App\Http\Controllers\Api\V1\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Api\V1\Admin\SellerInviteController as AdminSellerInviteController;
use App\Http\Controllers\Api\V1\Admin\SellerReportController as AdminSellerReportController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\StoreOversightController as AdminStoreOversightController;
use App\Http\Controllers\Api\V1\Admin\TopUpController as AdminTopUpController;
use App\Http\Controllers\Api\V1\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\V1\Admin\WalletFundingController as AdminWalletFundingController;
use App\Http\Controllers\Api\V1\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\V1\Seller\AccountController as SellerAccountController;
use App\Http\Controllers\Api\V1\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Api\V1\Seller\DisputeController as SellerDisputeController;
use App\Http\Controllers\Api\V1\Seller\FollowerController as SellerFollowerController;
use App\Http\Controllers\Api\V1\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\V1\Seller\PaymentMethodController as SellerPaymentMethodController;
use App\Http\Controllers\Api\V1\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\V1\Seller\PromotionController as SellerPromotionController;
use App\Http\Controllers\Api\V1\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Api\V1\Seller\StoreController as SellerStoreController;
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

        Route::middleware(['role:seller', 'seller.approved'])->prefix('seller')->group(function () {
            Route::get('/dashboard', [SellerDashboardController::class, 'show']);

            Route::get('/orders', [SellerOrderController::class, 'index']);
            Route::get('/orders/{orderItem}', [SellerOrderController::class, 'show']);
            Route::get('/orders/{orderItem}/pdf', [SellerOrderController::class, 'pdf']);
            Route::post('/orders/{orderItem}', [SellerOrderController::class, 'update']);
            Route::post('/orders/{orderItem}/reject', [SellerOrderController::class, 'reject']);
            Route::post('/orders/{orderItem}/confirm-direct-payment', [SellerOrderController::class, 'confirmDirectPayment']);
            Route::post('/orders/{orderItem}/reject-direct-payment', [SellerOrderController::class, 'rejectDirectPayment']);

            Route::get('/products', [SellerProductController::class, 'index']);
            Route::post('/products', [SellerProductController::class, 'store']);
            Route::post('/products/bulk', [SellerProductController::class, 'bulk']);
            Route::get('/products/{product}', [SellerProductController::class, 'show']);
            Route::patch('/products/{product}', [SellerProductController::class, 'update']);
            Route::post('/products/{product}/images', [SellerProductController::class, 'uploadImages']);
            Route::post('/products/{product}/visibility', [SellerProductController::class, 'toggleVisibility']);
            Route::post('/products/{product}/duplicate', [SellerProductController::class, 'duplicate']);
            Route::post('/products/{product}/video', [SellerProductController::class, 'uploadVideo']);
            Route::get('/products/{product}/analytics', [SellerProductController::class, 'analytics']);
            Route::get('/products/{product}/reviews', [SellerProductController::class, 'reviews']);
            Route::delete('/products/{product}', [SellerProductController::class, 'destroy']);

            Route::get('/reviews', [SellerReviewController::class, 'index']);
            Route::post('/reviews/{review}/reply', [SellerReviewController::class, 'reply']);

            Route::get('/promotions', [SellerPromotionController::class, 'index']);
            Route::post('/promotions', [SellerPromotionController::class, 'store']);
            Route::patch('/promotions/{coupon}', [SellerPromotionController::class, 'update']);
            Route::delete('/promotions/{coupon}', [SellerPromotionController::class, 'destroy']);

            Route::get('/followers', [SellerFollowerController::class, 'index']);
            Route::get('/refunds', [SellerDisputeController::class, 'index']);

            Route::get('/store', [SellerStoreController::class, 'show']);
            Route::patch('/store', [SellerStoreController::class, 'update']);
            Route::post('/store/logo', [SellerStoreController::class, 'uploadLogo']);
            Route::post('/store/cover', [SellerStoreController::class, 'uploadCover']);
            Route::post('/store/hero', [SellerStoreController::class, 'uploadHero']);
            Route::post('/store/promo', [SellerStoreController::class, 'uploadPromo']);
            Route::post('/store/publish', [SellerStoreController::class, 'publish']);
            Route::post('/store/complete-setup', [SellerStoreController::class, 'completeSetup']);

            Route::get('/account', [SellerAccountController::class, 'show']);
            Route::patch('/account/order-sms', [SellerAccountController::class, 'updateOrderSms']);
            Route::post('/activation/pay', [SellerAccountController::class, 'payActivation']);

            Route::get('/payment-methods', [SellerPaymentMethodController::class, 'index']);
            Route::patch('/payment-methods/settings', [SellerPaymentMethodController::class, 'updateSettings']);
            Route::post('/payment-methods', [SellerPaymentMethodController::class, 'store']);
            Route::delete('/payment-methods/{method}', [SellerPaymentMethodController::class, 'destroy']);
        });

        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'show']);

            Route::get('/sellers', [AdminSellerController::class, 'index']);
            Route::get('/sellers/{seller}', [AdminSellerController::class, 'show']);
            Route::post('/sellers/{seller}/approve', [AdminSellerController::class, 'approve']);
            Route::post('/sellers/{seller}/reject', [AdminSellerController::class, 'reject']);
            Route::post('/sellers/{seller}/block', [AdminSellerController::class, 'block']);
            Route::post('/sellers/{seller}/unblock', [AdminSellerController::class, 'unblock']);
            Route::post('/sellers/{seller}/activation/prompt', [AdminSellerController::class, 'promptActivation']);
            Route::post('/sellers/{seller}/activation/waive', [AdminSellerController::class, 'waiveActivation']);
            Route::post('/sellers/{seller}/activation/end', [AdminSellerController::class, 'endActivation']);
            Route::patch('/sellers/{seller}/profile', [AdminSellerController::class, 'updateProfile']);
            Route::delete('/sellers/{seller}', [AdminSellerController::class, 'destroy']);
            Route::post('/sellers/{seller}/resend-invite', [AdminSellerController::class, 'resendInvite']);
            Route::post('/sellers/{seller}/payment-methods/{method}/disable', [AdminSellerController::class, 'disablePaymentMethod']);
            Route::post('/sellers/{seller}/payment-methods/{method}/enable', [AdminSellerController::class, 'enablePaymentMethod']);
            Route::post('/sellers/{seller}/payment-methods/unlock', [AdminSellerController::class, 'unlockPaymentMethods']);

            Route::get('/products', [AdminProductController::class, 'index']);
            Route::post('/products/{product}/approve', [AdminProductController::class, 'approve']);
            Route::post('/products/{product}/reject', [AdminProductController::class, 'reject']);
            Route::post('/products/{product}/hide', [AdminProductController::class, 'hide']);

            Route::get('/orders', [AdminOrderController::class, 'index']);
            Route::get('/orders/unprocessed', [AdminOrderController::class, 'unprocessed']);
            Route::get('/orders/awaiting-confirmation', [AdminOrderController::class, 'awaitingConfirmation']);
            Route::get('/orders/awaiting-direct', [AdminOrderController::class, 'awaitingDirect']);
            Route::get('/orders/cancellations', [AdminOrderController::class, 'cancellations']);
            Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
            Route::post('/orders/items/{orderItem}/cancel-unprocessed', [AdminOrderController::class, 'cancelUnprocessed']);
            Route::post('/orders/items/{orderItem}/confirm-delivery', [AdminOrderController::class, 'confirmDelivery']);

            Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
            Route::post('/withdrawals/{withdrawal}/start', [AdminWithdrawalController::class, 'start']);
            Route::post('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve']);
            Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject']);

            Route::get('/top-ups', [AdminTopUpController::class, 'index']);
            Route::post('/top-ups/{topUp}/amount', [AdminTopUpController::class, 'updateAmount']);
            Route::post('/top-ups/{topUp}/approve', [AdminTopUpController::class, 'approve']);
            Route::post('/top-ups/{topUp}/reject', [AdminTopUpController::class, 'reject']);

            Route::get('/wallet-funding/users', [AdminWalletFundingController::class, 'users']);
            Route::post('/wallet-funding', [AdminWalletFundingController::class, 'store']);

            Route::get('/disputes', [AdminDisputeController::class, 'index']);
            Route::post('/disputes/{dispute}/review', [AdminDisputeController::class, 'review']);
            Route::post('/disputes/{dispute}/resolve', [AdminDisputeController::class, 'resolve']);

            Route::get('/pending-funds', [AdminPendingFundController::class, 'index']);
            Route::post('/pending-funds/{orderItem}/release', [AdminPendingFundController::class, 'approve']);
            Route::post('/pending-funds/{orderItem}/hold', [AdminPendingFundController::class, 'reject']);

            Route::get('/categories', [AdminCategoryController::class, 'index']);
            Route::post('/categories', [AdminCategoryController::class, 'store']);
            Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

            Route::get('/buyers', [AdminBuyerController::class, 'index']);
            Route::get('/buyers/{buyer}', [AdminBuyerController::class, 'show']);
            Route::patch('/buyers/{buyer}', [AdminBuyerController::class, 'update']);
            Route::post('/buyers/{buyer}/block', [AdminBuyerController::class, 'block']);
            Route::post('/buyers/{buyer}/unblock', [AdminBuyerController::class, 'unblock']);
            Route::delete('/buyers/{buyer}', [AdminBuyerController::class, 'destroy']);

            Route::get('/kyc', [AdminKycController::class, 'index']);
            Route::get('/kyc/{kyc}', [AdminKycController::class, 'show']);
            Route::post('/kyc/{kyc}/approve', [AdminKycController::class, 'approve']);
            Route::post('/kyc/{kyc}/reject', [AdminKycController::class, 'reject']);
            Route::post('/kyc/{kyc}/request-changes', [AdminKycController::class, 'requestChanges']);

            Route::get('/seller-invites', [AdminSellerInviteController::class, 'index']);
            Route::post('/seller-invites', [AdminSellerInviteController::class, 'store']);

            Route::get('/stores', [AdminStoreOversightController::class, 'index']);
            Route::get('/stores/{seller}', [AdminStoreOversightController::class, 'show']);
            Route::post('/stores/{seller}/products/bulk', [AdminStoreOversightController::class, 'bulkProducts']);
            Route::post('/stores/{seller}/products/{product}/hide', [AdminStoreOversightController::class, 'hideProduct']);
            Route::post('/stores/{seller}/products/{product}/approve', [AdminStoreOversightController::class, 'approveProduct']);
            Route::delete('/stores/{seller}/products/{product}', [AdminStoreOversightController::class, 'destroyProduct']);
            Route::post('/stores/{seller}/products/{product}/restore', [AdminStoreOversightController::class, 'restoreProduct']);

            Route::get('/chats', [AdminChatController::class, 'index']);
            Route::get('/chats/{conversation}', [AdminChatController::class, 'show']);

            Route::get('/contact-messages', [AdminContactMessageController::class, 'index']);
            Route::patch('/contact-messages/{contactMessage}/read', [AdminContactMessageController::class, 'markRead']);

            Route::get('/announcements', [AdminAnnouncementController::class, 'sellerIndex']);
            Route::get('/announcements/recipients', [AdminAnnouncementController::class, 'sellerRecipients']);
            Route::post('/announcements', [AdminAnnouncementController::class, 'sellerStore']);
            Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'sellerDestroy']);
            Route::get('/buyer-announcements', [AdminAnnouncementController::class, 'buyerIndex']);
            Route::get('/buyer-announcements/recipients', [AdminAnnouncementController::class, 'buyerRecipients']);
            Route::post('/buyer-announcements', [AdminAnnouncementController::class, 'buyerStore']);
            Route::delete('/buyer-announcements/{buyerAnnouncement}', [AdminAnnouncementController::class, 'buyerDestroy']);

            Route::get('/transactions', [AdminTransactionController::class, 'index']);
            Route::get('/seller-reports', [AdminSellerReportController::class, 'index']);
            Route::patch('/seller-reports/{report}', [AdminSellerReportController::class, 'update']);

            Route::get('/settings/sms', [AdminSettingsController::class, 'sms']);
            Route::post('/settings/sms', [AdminSettingsController::class, 'updateSms']);
            Route::post('/settings/sms/test', [AdminSettingsController::class, 'testSms']);
            Route::get('/settings/paystack', [AdminSettingsController::class, 'paystack']);
            Route::post('/settings/paystack', [AdminSettingsController::class, 'updatePaystack']);
            Route::post('/settings/paystack/lock', [AdminSettingsController::class, 'updatePaystackLock']);
            Route::get('/settings/withdrawal', [AdminSettingsController::class, 'withdrawal']);
            Route::post('/settings/withdrawal', [AdminSettingsController::class, 'updateWithdrawal']);
            Route::get('/settings/manual-funding', [AdminSettingsController::class, 'manualFunding']);
            Route::post('/settings/manual-funding', [AdminSettingsController::class, 'updateManualFunding']);

            Route::get('/china-transfers', [AdminChinaTransferController::class, 'index']);
            Route::get('/china-transfers/settings', [AdminChinaTransferController::class, 'settings']);
            Route::post('/china-transfers/settings', [AdminChinaTransferController::class, 'updateSettings']);
            Route::post('/china-transfers/rates', [AdminChinaTransferController::class, 'publishRate']);
            Route::post('/china-transfers/methods/{method}/deactivate', [AdminChinaTransferController::class, 'deactivateMethod']);
            Route::post('/china-transfers/fields/{field}/deactivate', [AdminChinaTransferController::class, 'deactivateField']);
            Route::get('/china-transfers/{chinaTransfer}', [AdminChinaTransferController::class, 'show']);
            Route::post('/china-transfers/{chinaTransfer}/verify', [AdminChinaTransferController::class, 'verify']);
            Route::post('/china-transfers/{chinaTransfer}/reject', [AdminChinaTransferController::class, 'reject']);
            Route::post('/china-transfers/{chinaTransfer}/process', [AdminChinaTransferController::class, 'process']);
            Route::post('/china-transfers/{chinaTransfer}/sent', [AdminChinaTransferController::class, 'markSent']);
            Route::post('/china-transfers/{chinaTransfer}/complete', [AdminChinaTransferController::class, 'complete']);
            Route::post('/china-transfers/{chinaTransfer}/fail', [AdminChinaTransferController::class, 'fail']);
            Route::post('/china-transfers/{chinaTransfer}/cancel', [AdminChinaTransferController::class, 'cancel']);
            Route::post('/china-transfers/{chinaTransfer}/note', [AdminChinaTransferController::class, 'note']);

            Route::get('/sell-rmb', [AdminSellRmbController::class, 'index']);
            Route::get('/sell-rmb/settings', [AdminSellRmbController::class, 'settings']);
            Route::post('/sell-rmb/settings', [AdminSellRmbController::class, 'updateSettings']);
            Route::post('/sell-rmb/rates', [AdminSellRmbController::class, 'publishRate']);
            Route::post('/sell-rmb/methods/{method}/deactivate', [AdminSellRmbController::class, 'deactivateMethod']);
            Route::post('/sell-rmb/fields/{field}/deactivate', [AdminSellRmbController::class, 'deactivateField']);
            Route::get('/sell-rmb/{sellRmbTransfer}', [AdminSellRmbController::class, 'show']);
            Route::post('/sell-rmb/{sellRmbTransfer}/verify', [AdminSellRmbController::class, 'verify']);
            Route::post('/sell-rmb/{sellRmbTransfer}/received', [AdminSellRmbController::class, 'received']);
            Route::post('/sell-rmb/{sellRmbTransfer}/process', [AdminSellRmbController::class, 'process']);
            Route::post('/sell-rmb/{sellRmbTransfer}/paid', [AdminSellRmbController::class, 'paid']);
            Route::post('/sell-rmb/{sellRmbTransfer}/complete', [AdminSellRmbController::class, 'complete']);
            Route::post('/sell-rmb/{sellRmbTransfer}/reject', [AdminSellRmbController::class, 'reject']);
            Route::post('/sell-rmb/{sellRmbTransfer}/fail', [AdminSellRmbController::class, 'fail']);
            Route::post('/sell-rmb/{sellRmbTransfer}/cancel', [AdminSellRmbController::class, 'cancel']);
            Route::post('/sell-rmb/{sellRmbTransfer}/note', [AdminSellRmbController::class, 'note']);
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

        Route::get('/kyc', [KycController::class, 'show']);
        Route::post('/kyc', [KycController::class, 'store']);

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
        Route::post('/messages/{conversation}/members/{user}/block', [MessageController::class, 'blockMember']);
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
        Route::post('/messages/{conversation}/messages/{message}/view-once', [MessageController::class, 'openViewOnce']);

        Route::get('/status', [\App\Http\Controllers\Api\V1\StatusController::class, 'index']);
        Route::post('/status', [\App\Http\Controllers\Api\V1\StatusController::class, 'store']);
        Route::post('/status/{status}/view', [\App\Http\Controllers\Api\V1\StatusController::class, 'view']);
        Route::get('/status/{status}/views', [\App\Http\Controllers\Api\V1\StatusController::class, 'views']);
        Route::delete('/status/{status}', [\App\Http\Controllers\Api\V1\StatusController::class, 'destroy']);

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
        Route::post('/checkout/apply-coupons', [CheckoutController::class, 'applyCoupons']);
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
        Route::post('/wallet/convert/quote', [WalletController::class, 'convertQuote']);
        Route::post('/wallet/convert', [WalletController::class, 'convert']);
        Route::get('/wallet/china-transfer', [ChinaTransferController::class, 'index']);
        Route::post('/wallet/china-transfer/quote', [ChinaTransferController::class, 'quote']);
        Route::post('/wallet/china-transfer', [ChinaTransferController::class, 'store']);
        Route::get('/wallet/china-transfer/{chinaTransfer}', [ChinaTransferController::class, 'show']);
        Route::post('/wallet/china-transfer/{chinaTransfer}/cancel', [ChinaTransferController::class, 'cancel']);
        Route::get('/wallet/sell-rmb', [SellRmbController::class, 'index']);
        Route::post('/wallet/sell-rmb/quote', [SellRmbController::class, 'quote']);
        Route::post('/wallet/sell-rmb', [SellRmbController::class, 'store']);
        Route::get('/wallet/sell-rmb/{sellRmbTransfer}', [SellRmbController::class, 'show']);
        Route::post('/wallet/sell-rmb/{sellRmbTransfer}/cancel', [SellRmbController::class, 'cancel']);
        Route::post('/wallet/paystack/initialize', [WalletController::class, 'initializePaystackTopUp']);
        Route::post('/wallet/paystack/verify', [WalletController::class, 'verifyPaystackTopUp']);
        Route::get('/wallet/qr/receive', [QrPaymentController::class, 'receive']);
        Route::post('/wallet/qr/resolve', [QrPaymentController::class, 'resolve']);
        Route::post('/wallet/qr/pay', [QrPaymentController::class, 'pay']);
    });
});
