<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AvatarController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TelegramLoginController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('register', [RegistrationController::class, 'create'])->name('register');
    Route::post('register', [RegistrationController::class, 'store'])->middleware('throttle:registration')->name('register.store');
    Route::get('login', [SessionController::class, 'create'])->name('login');
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('recover', [RecoveryController::class, 'create'])->name('recover');
    Route::post('recover', [RecoveryController::class, 'store'])->middleware('throttle:recovery')->name('recover.store');
});
Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth.session', 'throttle:login'])->prefix('telegram/login-attempts')->name('telegram.login.')->group(function (): void {
    Route::post('/', [TelegramLoginController::class, 'store'])->name('store');
    Route::get('{attempt}', [TelegramLoginController::class, 'show'])->whereNumber('attempt')->name('show');
    Route::patch('{attempt}', [TelegramLoginController::class, 'update'])->whereNumber('attempt')->name('update');
    Route::delete('{attempt}', [TelegramLoginController::class, 'destroy'])->whereNumber('attempt')->name('destroy');
});
Route::get('profile/avatar', AvatarController::class)->middleware(['auth', 'auth.session'])->name('profile.avatar');
