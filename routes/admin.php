<?php

declare(strict_types=1);
use App\Http\Controllers\Admin\EconomySettingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'can:manage-system-settings'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('economy', [EconomySettingController::class, 'index'])->name('economy.index');
    Route::put('economy', [EconomySettingController::class, 'update'])->middleware('throttle:couple-actions')->name('economy.update');
});
