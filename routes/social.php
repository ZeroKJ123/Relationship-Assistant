<?php

declare(strict_types=1);
use App\Http\Controllers\DateInvitationController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GuestVisitController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('friends', [FriendController::class, 'index'])->name('friends.index');
    Route::get('friends/create', [FriendController::class, 'create'])->name('friends.create');
    Route::post('friends', [FriendController::class, 'store'])->middleware('throttle:couple-actions')->name('friends.store');
    Route::get('friends/{friend}/edit', [FriendController::class, 'edit'])->whereNumber('friend')->name('friends.edit');
    Route::put('friends/{friend}', [FriendController::class, 'update'])->whereNumber('friend')->middleware('throttle:couple-actions')->name('friends.update');
    Route::delete('friends/{friend}', [FriendController::class, 'destroy'])->whereNumber('friend')->middleware('throttle:couple-actions')->name('friends.destroy');
    Route::middleware(EnsureCoupleAccess::class)->prefix('couples/{couple}')->whereNumber('couple')->group(function (): void {
        Route::get('guests/create', [GuestVisitController::class, 'create'])->name('guests.create');
        Route::post('guests/preview', [GuestVisitController::class, 'create'])->middleware('throttle:couple-actions')->name('guests.preview');
        Route::post('guests', [GuestVisitController::class, 'store'])->middleware('throttle:couple-actions')->name('guests.store');
        Route::get('dates/create', [DateInvitationController::class, 'create'])->name('dates.create');
        Route::post('dates', [DateInvitationController::class, 'store'])->middleware('throttle:couple-actions')->name('dates.store');
    });
});
