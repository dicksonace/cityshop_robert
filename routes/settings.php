<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PaymentPinController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('settings/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/payment-pin', [PaymentPinController::class, 'edit'])->name('payment-pin.edit');
    Route::post('settings/payment-pin', [PaymentPinController::class, 'store'])->name('payment-pin.store');
    Route::put('settings/payment-pin', [PaymentPinController::class, 'update'])->name('payment-pin.update');
    Route::post('settings/payment-pin/forgot', [PaymentPinController::class, 'forgot'])->name('payment-pin.forgot');
    Route::post('settings/payment-pin/reset', [PaymentPinController::class, 'reset'])->name('payment-pin.reset');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
