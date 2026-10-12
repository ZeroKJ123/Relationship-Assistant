<?php

declare(strict_types=1);
use App\Http\Controllers\Admin\EconomySettingController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\SystemRequestController;
use App\Http\Middleware\EnsurePanelAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'can:manage-system-settings'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('economy', [EconomySettingController::class, 'index'])->name('economy.index');
    Route::put('economy', [EconomySettingController::class, 'update'])->middleware('throttle:couple-actions')->name('economy.update');
});

Route::middleware(['auth', 'auth.session'])->prefix('admin-panel')->name('admin.panel.')->group(function (): void {
    Route::get('login', [PanelController::class, 'create'])->name('login');
    Route::post('login', [PanelController::class, 'store'])->middleware('throttle:recovery')->name('unlock');
    Route::middleware(EnsurePanelAccess::class)->group(function (): void {
        Route::get('/', [PanelController::class, 'index'])->name('index');
        Route::post('act-as', [PanelController::class, 'update'])->middleware('throttle:couple-actions')->name('act_as');
        Route::post('stop-acting', [PanelController::class, 'stop'])->name('stop');
        Route::post('lock', [PanelController::class, 'destroy'])->name('lock');
        Route::post('system-requests', SystemRequestController::class)->middleware('throttle:couple-actions')->name('system_requests');
    });
});
