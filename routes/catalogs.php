<?php

declare(strict_types=1);
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::post('catalogs/methods', [CatalogController::class, 'store'])->middleware('throttle:couple-actions')->name('catalogs.methods.store');
});
