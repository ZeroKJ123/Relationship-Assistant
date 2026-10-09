<?php

declare(strict_types=1);

use App\Http\Controllers\PartnerRequestController;
use App\Http\Controllers\RequestCancellationController;
use App\Http\Controllers\RequestResponseController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', EnsureCoupleAccess::class])->prefix('couples/{couple}/requests')->whereNumber('couple')->group(function (): void {
    Route::post('{partnerRequest}/cancel', RequestCancellationController::class)->whereNumber('partnerRequest')->middleware('throttle:couple-actions')->name('requests.cancel');
    Route::get('', [PartnerRequestController::class, 'index'])->name('requests.index');
    Route::get('create', [PartnerRequestController::class, 'create'])->name('requests.create');
    Route::post('', [PartnerRequestController::class, 'store'])->middleware('throttle:couple-actions')->name('requests.store');
    Route::get('{partnerRequest}', [PartnerRequestController::class, 'show'])->whereNumber('partnerRequest')->name('requests.show');
    Route::post('{partnerRequest}/responses', [RequestResponseController::class, 'store'])->whereNumber('partnerRequest')->middleware('throttle:couple-actions')->name('requests.responses.store');
    Route::post('{partnerRequest}/condition', [RequestResponseController::class, 'condition'])->whereNumber('partnerRequest')->middleware('throttle:couple-actions')->name('requests.condition.store');
});
