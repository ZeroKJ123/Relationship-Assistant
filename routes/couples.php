<?php

declare(strict_types=1);

use App\Http\Controllers\CoupleController;
use App\Http\Controllers\CoupleEndController;
use App\Http\Controllers\CoupleInvitationController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('couples', [CoupleController::class, 'index'])->name('couples.index');
    Route::get('couples/create', [CoupleInvitationController::class, 'create'])->name('couples.create');
    Route::post('couples/invitations', [CoupleInvitationController::class, 'store'])->middleware('throttle:couple-actions')->name('invitations.store');
    Route::delete('couples/invitations/{invitation}', [CoupleInvitationController::class, 'destroy'])->whereNumber('invitation')->name('invitations.destroy');
    Route::get('couples/{couple}', [CoupleController::class, 'show'])->whereNumber('couple')->middleware(EnsureCoupleAccess::class)->name('couples.show');
    Route::patch('couples/{couple}/dates', [CoupleController::class, 'update'])->whereNumber('couple')->middleware([EnsureCoupleAccess::class, 'throttle:couple-actions'])->name('couples.dates');
    Route::get('couples/{couple}/end', [CoupleEndController::class, 'create'])->whereNumber('couple')->middleware(EnsureCoupleAccess::class)->name('couples.end');
    Route::post('couples/{couple}/end', [CoupleEndController::class, 'store'])->whereNumber('couple')->middleware(EnsureCoupleAccess::class)->name('couples.end_store');
    Route::get('invitations/{token}', [CoupleInvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->name('invitations.show');
    Route::post('invitations/{token}', [CoupleInvitationController::class, 'update'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:couple-actions')->name('invitations.accept');
});
