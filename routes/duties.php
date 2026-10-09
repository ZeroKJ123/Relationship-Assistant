<?php

declare(strict_types=1);

use App\Http\Controllers\DutyController;
use App\Http\Controllers\HeartController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', EnsureCoupleAccess::class])->prefix('couples/{couple}')->whereNumber('couple')->group(function (): void {
    Route::get('duties', [DutyController::class, 'index'])->name('duties.index');
    Route::get('duties/{duty}', [DutyController::class, 'show'])->whereNumber('duty')->name('duties.show');
    Route::post('duties/{duty}/report', [DutyController::class, 'report'])->whereNumber('duty')->middleware('throttle:couple-actions')->name('duties.report');
    Route::post('duties/{duty}/verify', [DutyController::class, 'verify'])->whereNumber('duty')->middleware('throttle:couple-actions')->name('duties.verify');
    Route::post('hearts', [HeartController::class, 'store'])->middleware('throttle:couple-actions')->name('hearts.store');
});
Route::get('hearts', [HeartController::class, 'index'])->middleware(['auth', 'auth.session'])->name('hearts.index');
