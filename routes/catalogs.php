<?php

declare(strict_types=1);
use App\Http\Controllers\CarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\MovieController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('cars', [CarController::class, 'index'])->name('cars.index');
    Route::post('cars', [CarController::class, 'store'])->middleware('throttle:couple-actions')->name('cars.store');
    Route::get('movies', [MovieController::class, 'index'])->name('movies.index');
    Route::post('movies', [MovieController::class, 'store'])->middleware('throttle:couple-actions')->name('movies.store');
    Route::delete('movies/{movie}', [MovieController::class, 'destroy'])->whereNumber('movie')->middleware('throttle:couple-actions')->name('movies.destroy');
    Route::get('couples/{couple}/movies', [MovieController::class, 'show'])->whereNumber('couple')->middleware(EnsureCoupleAccess::class)->name('movies.choice');
    Route::post('couples/{couple}/movies/{movie}/votes', [MovieController::class, 'vote'])->whereNumber(['couple', 'movie'])->middleware([EnsureCoupleAccess::class, 'throttle:couple-actions'])->name('movies.vote');
    Route::post('couples/{couple}/movies/draw', [MovieController::class, 'draw'])->whereNumber('couple')->middleware([EnsureCoupleAccess::class, 'throttle:couple-actions'])->name('movies.draw');
    Route::get('catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::post('catalogs/methods', [CatalogController::class, 'store'])->middleware('throttle:couple-actions')->name('catalogs.methods.store');
});
