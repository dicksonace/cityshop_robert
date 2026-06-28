<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\SellerInviteController as AdminSellerInviteController;
use App\Http\Controllers\Admin\StoreOversightController as AdminStoreOversightController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Chat\ConversationController as ChatConversationController;
use App\Http\Controllers\Chat\MessageController as ChatMessageController;
use App\Http\Controllers\Chat\NotificationController as ChatNotificationController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Seller\CouponController as SellerCouponController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\PaymentMethodController as SellerPaymentMethodController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\ReviewController as SellerReviewController;
use App\Http\Controllers\Seller\StoreCustomizationController as SellerStoreCustomizationController;
use App\Http\Controllers\Seller\WalletController as SellerWalletController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CheckoutSessionController;
use App\Http\Controllers\Shop\ContactController;
use App\Http\Controllers\Shop\DisputeController;
use App\Http\Controllers\Shop\FaqController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ImageSearchController;
use App\Http\Controllers\Shop\InvoiceController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\SearchController;
use App\Http\Controllers\Shop\StoreController;
use App\Http\Controllers\Shop\WalletController as BuyerWalletController;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
Route::get('/search/image', [ImageSearchController::class, 'create'])->name('search.image');
Route::post('/search/image', [ImageSearchController::class, 'store'])->name('search.image.store');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/store/{slug}', [StoreController::class, 'show'])->name('store.show');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::get('/faq', [FaqController::class, 'show'])->name('faq');

Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle']);

Route::middleware(['auth'])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/payment/{checkout}', [CheckoutController::class, 'payment'])->name('checkout.payment');
    Route::get('/checkout/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
    Route::post('/checkout/payment/{checkout}/initialize', [CheckoutController::class, 'initializePayment'])->name('checkout.initialize');
    Route::get('/checkouts/{checkout}', [CheckoutSessionController::class, 'show'])->name('checkouts.show');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/orders/{order}/direct-payment', [CheckoutController::class, 'submitDirectReference'])->name('orders.direct-payment');

    Route::get('/my-orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/my-orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/my-orders/{order}/reviews', [ReviewController::class, 'store'])->name('orders.reviews.store');
    Route::post('/my-orders/{order}/disputes', [DisputeController::class, 'store'])->name('orders.disputes.store');
    Route::post('/products/{product:slug}/reviews', [ReviewController::class, 'storeForProduct'])->name('products.reviews.store');

    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/wallet', [BuyerWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/add-funds', [BuyerWalletController::class, 'addFunds'])->name('wallet.add-funds');
    Route::post('/wallet/withdraw', [BuyerWalletController::class, 'withdraw'])->name('wallet.withdraw');

    Route::get('/messages', [ChatConversationController::class, 'index'])->name('chat.index');
    Route::post('/messages', [ChatConversationController::class, 'store'])->name('chat.store');
    Route::get('/messages/{conversation}', [ChatConversationController::class, 'show'])->name('chat.show');
    Route::get('/messages/{conversation}/poll', [ChatConversationController::class, 'poll'])->name('chat.poll');
    Route::post('/messages/{conversation}/send', [ChatMessageController::class, 'store'])->name('chat.messages.store');
    Route::post('/messages/{conversation}/image', [ChatMessageController::class, 'uploadImage'])->name('chat.messages.image');
    Route::patch('/messages/{conversation}/messages/{message}', [ChatMessageController::class, 'update'])->name('chat.messages.update');
    Route::delete('/messages/{conversation}/messages/{message}', [ChatMessageController::class, 'destroy'])->name('chat.messages.destroy');
    Route::post('/messages/{conversation}/signal', [ChatMessageController::class, 'signal'])->name('chat.signal');

    Route::get('/notifications', [ChatNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [ChatNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [ChatNotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/counts', [ChatNotificationController::class, 'counts'])->name('notifications.counts');
});

Route::prefix('seller')->name('seller.')->middleware(['auth', 'role:seller'])->group(function () {
    Route::get('/pending', [SellerDashboardController::class, 'pending'])->name('pending');

    Route::middleware(['seller.approved'])->group(function () {
        Route::get('/store-setup', [SellerStoreCustomizationController::class, 'setup'])->name('store-setup');
        Route::get('/store-appearance', [SellerStoreCustomizationController::class, 'appearance'])->name('store-appearance.index');
        Route::post('/store-appearance/draft', [SellerStoreCustomizationController::class, 'updateDraft'])->name('store-appearance.draft');
        Route::post('/store-appearance/publish', [SellerStoreCustomizationController::class, 'publish'])->name('store-appearance.publish');
        Route::post('/store-appearance/reset', [SellerStoreCustomizationController::class, 'reset'])->name('store-appearance.reset');
        Route::post('/store-appearance/complete-setup', [SellerStoreCustomizationController::class, 'completeSetup'])->name('store-appearance.complete-setup');

        Route::middleware(['seller.store-setup'])->group(function () {
            Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');

            Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
            Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
            Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
            Route::post('/products/bulk', [SellerProductController::class, 'bulk'])->name('products.bulk');
            Route::get('/products/{product}/edit', [SellerProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [SellerProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{product}/duplicate', [SellerProductController::class, 'duplicate'])->name('products.duplicate');
            Route::patch('/products/{product}/visibility', [SellerProductController::class, 'toggleVisibility'])->name('products.visibility');
            Route::get('/products/{product}/analytics', [SellerProductController::class, 'analytics'])->name('products.analytics');
            Route::get('/products/{product}/reviews', [SellerProductController::class, 'reviews'])->name('products.reviews');

            Route::get('/promotions', [SellerCouponController::class, 'index'])->name('promotions.index');
            Route::post('/promotions', [SellerCouponController::class, 'store'])->name('promotions.store');
            Route::patch('/promotions/{coupon}', [SellerCouponController::class, 'update'])->name('promotions.update');
            Route::delete('/promotions/{coupon}', [SellerCouponController::class, 'destroy'])->name('promotions.destroy');

            Route::get('/reviews', [SellerReviewController::class, 'index'])->name('reviews.index');
            Route::post('/reviews/{review}/reply', [SellerReviewController::class, 'reply'])->name('reviews.reply');

            Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{orderItem}', [SellerOrderController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{orderItem}', [SellerOrderController::class, 'update'])->name('orders.update');
            Route::post('/orders/{orderItem}/reject', [SellerOrderController::class, 'reject'])->name('orders.reject');

            Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet');
            Route::post('/wallet/withdraw', [SellerWalletController::class, 'withdraw'])->name('wallet.withdraw');
            Route::post('/wallet/payout-methods', [SellerWalletController::class, 'storePayoutMethod'])->name('wallet.payout-methods.store');
            Route::delete('/wallet/payout-methods/{payoutMethod}', [SellerWalletController::class, 'destroyPayoutMethod'])->name('wallet.payout-methods.destroy');

            Route::get('/payment-methods', [SellerPaymentMethodController::class, 'index'])->name('payment-methods.index');
            Route::post('/payment-methods/settings', [SellerPaymentMethodController::class, 'updateSettings'])->name('payment-methods.settings');
            Route::post('/payment-methods', [SellerPaymentMethodController::class, 'store'])->name('payment-methods.store');
            Route::delete('/payment-methods/{method}', [SellerPaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
            Route::post('/orders/{order}/confirm-direct-payment', [SellerPaymentMethodController::class, 'confirmDirectPayment'])->name('orders.confirm-direct-payment');
        });
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/{seller}', [AdminSellerController::class, 'show'])->name('sellers.show');
    Route::post('/sellers/{seller}/approve', [AdminSellerController::class, 'approve'])->name('sellers.approve');
    Route::post('/sellers/{seller}/reject', [AdminSellerController::class, 'reject'])->name('sellers.reject');
    Route::post('/sellers/{seller}/block', [AdminSellerController::class, 'block'])->name('sellers.block');
    Route::post('/sellers/{seller}/unblock', [AdminSellerController::class, 'unblock'])->name('sellers.unblock');
    Route::post('/sellers/{seller}/resend-invite', [AdminSellerInviteController::class, 'resendForSeller'])->name('sellers.resend-invite');

    Route::get('/seller-invites', [AdminSellerInviteController::class, 'index'])->name('seller-invites.index');
    Route::post('/seller-invites', [AdminSellerInviteController::class, 'store'])->name('seller-invites.store');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawal}/approve', [AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');

    Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes.index');
    Route::post('/disputes/{dispute}/resolve', [AdminDisputeController::class, 'resolve'])->name('disputes.resolve');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::patch('/contact-messages/{contactMessage}/read', [AdminContactMessageController::class, 'markRead'])->name('contact-messages.read');

    Route::get('/stores', [AdminStoreOversightController::class, 'index'])->name('stores.index');
    Route::get('/stores/{seller}', [AdminStoreOversightController::class, 'show'])->name('stores.show');
    Route::post('/stores/{seller}/products/bulk', [AdminStoreOversightController::class, 'bulkProducts'])->name('stores.products.bulk');
    Route::post('/stores/{seller}/products/{product}/hide', [AdminStoreOversightController::class, 'hideProduct'])->name('stores.products.hide');
    Route::post('/stores/{seller}/products/{product}/approve', [AdminStoreOversightController::class, 'approveProduct'])->name('stores.products.approve');
    Route::delete('/stores/{seller}/products/{product}', [AdminStoreOversightController::class, 'destroyProduct'])->name('stores.products.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
