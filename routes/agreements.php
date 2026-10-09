<?php

declare(strict_types=1);

use App\Http\Controllers\AgreementApprovalController;
use App\Http\Controllers\AgreementProposalController;
use App\Http\Controllers\CoupleAgreementController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', EnsureCoupleAccess::class])->prefix('couples/{couple}/agreement')->whereNumber('couple')->group(function (): void {
    Route::get('', [CoupleAgreementController::class, 'show'])->name('agreements.show');
    Route::post('answers', [CoupleAgreementController::class, 'store'])->middleware('throttle:couple-actions')->name('agreements.answers.store');
    Route::post('proposals', [AgreementProposalController::class, 'store'])->middleware('throttle:couple-actions')->name('agreements.proposals.store');
    Route::post('proposals/{proposal}/approvals', [AgreementApprovalController::class, 'store'])->whereNumber('proposal')->middleware('throttle:couple-actions')->name('agreements.approvals.store');
    Route::delete('proposals/{proposal}/approvals', [AgreementApprovalController::class, 'destroy'])->whereNumber('proposal')->middleware('throttle:couple-actions')->name('agreements.approvals.destroy');
});
