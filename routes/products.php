<?php

declare(strict_types=1);

use App\Http\Controllers\DishController;
use App\Http\Controllers\MoneyRequestController;
use App\Http\Controllers\PrivatePhotoController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\WishlistItemController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('dishes', [DishController::class, 'index'])->name('dishes.index');
    Route::get('dishes/create', [DishController::class, 'create'])->name('dishes.create');
    Route::post('dishes', [DishController::class, 'store'])->middleware('throttle:couple-actions')->name('dishes.store');
    Route::get('dishes/{dish}/edit', [DishController::class, 'edit'])->whereNumber('dish')->name('dishes.edit');
    Route::put('dishes/{dish}', [DishController::class, 'update'])->whereNumber('dish')->middleware('throttle:couple-actions')->name('dishes.update');
    Route::get('rewards', [RewardController::class, 'index'])->name('rewards.index');
    Route::get('rewards/create', [RewardController::class, 'create'])->name('rewards.create');
    Route::post('rewards', [RewardController::class, 'store'])->middleware('throttle:couple-actions')->name('rewards.store');
    Route::get('rewards/{reward}/edit', [RewardController::class, 'edit'])->whereNumber('reward')->name('rewards.edit');
    Route::put('rewards/{reward}', [RewardController::class, 'update'])->whereNumber('reward')->middleware('throttle:couple-actions')->name('rewards.update');
    Route::delete('rewards/{reward}', [RewardController::class, 'destroy'])->whereNumber('reward')->middleware('throttle:couple-actions')->name('rewards.destroy');
    Route::get('wishlist', [WishlistItemController::class, 'index'])->name('wishlist.index');
    Route::get('wishlist/create', [WishlistItemController::class, 'create'])->name('wishlist.create');
    Route::post('wishlist', [WishlistItemController::class, 'store'])->middleware('throttle:couple-actions')->name('wishlist.store');
    Route::get('wishlist/{wishlistItem}/edit', [WishlistItemController::class, 'edit'])->whereNumber('wishlistItem')->name('wishlist.edit');
    Route::put('wishlist/{wishlistItem}', [WishlistItemController::class, 'update'])->whereNumber('wishlistItem')->middleware('throttle:couple-actions')->name('wishlist.update');
    Route::post('wishlist/{wishlistItem}/archive', [WishlistItemController::class, 'archive'])->whereNumber('wishlistItem')->middleware('throttle:couple-actions')->name('wishlist.archive');
    Route::get('photos/dishes/{dish}', [PrivatePhotoController::class, 'dish'])->whereNumber('dish')->name('photos.dishes');
    Route::get('photos/wishlist/{wishlistItem}', [PrivatePhotoController::class, 'wishlist'])->whereNumber('wishlistItem')->name('photos.wishlist');
    Route::get('photos/requests/{partnerRequest}', [PrivatePhotoController::class, 'request'])->whereNumber('partnerRequest')->name('photos.requests');
    Route::get('photos/conditions/{requestCondition}', [PrivatePhotoController::class, 'condition'])->whereNumber('requestCondition')->name('photos.conditions');
    Route::middleware(EnsureCoupleAccess::class)->prefix('couples/{couple}/money')->whereNumber('couple')->group(function (): void {
        Route::get('create', [MoneyRequestController::class, 'create'])->name('money.create');
        Route::post('', [MoneyRequestController::class, 'store'])->middleware('throttle:couple-actions')->name('money.store');
    });
});
