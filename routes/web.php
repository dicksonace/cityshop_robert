<?php

use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Chat\ConversationController as ChatConversationController;
use App\Http\Controllers\Chat\MessageController as ChatMessageController;
use App\Http\Controllers\Chat\NotificationController as ChatNotificationController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\WalletController as SellerWalletController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ContactController;
use App\Http\Controllers\Shop\DisputeController;
use App\Http\Controllers\Shop\FaqController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\StoreController;
use App\Http\Controllers\Shop\WalletController as BuyerWalletController;
use App\Http\Controllers\Shop\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
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
    Route::get('/checkout/payment/{order}', [CheckoutController::class, 'payment'])->name('checkout.payment');
    Route::get('/checkout/callback', [CheckoutController::class, 'callback'])->name('checkout.callback');
    Route::post('/checkout/payment/{order}/initialize', [CheckoutController::class, 'initializePayment'])->name('checkout.initialize');

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
        Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');

        Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
        Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [SellerProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [SellerProductController::class, 'destroy'])->name('products.destroy');
        Route::get('/products/{product}/reviews', [SellerProductController::class, 'reviews'])->name('products.reviews');

        Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
        Route::patch('/orders/{orderItem}', [SellerOrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{orderItem}/reject', [SellerOrderController::class, 'reject'])->name('orders.reject');

        Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet');
        Route::post('/wallet/withdraw', [SellerWalletController::class, 'withdraw'])->name('wallet.withdraw');
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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
