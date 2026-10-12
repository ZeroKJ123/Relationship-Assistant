<?php

declare(strict_types=1);

use App\Http\Controllers\ChoreDrawController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\MoodDayController;
use App\Http\Controllers\MoodStickerController;
use App\Http\Controllers\PartnerMoodPromptController;
use App\Http\Controllers\PartnerReminderController;
use App\Http\Middleware\EnsureCoupleAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', EnsureCoupleAccess::class])->prefix('couples/{couple}')->whereNumber('couple')->group(function (): void {
    Route::get('chore-draws', [ChoreDrawController::class, 'index'])->name('chores.index');
    Route::post('chore-draws', [ChoreDrawController::class, 'store'])->middleware('throttle:couple-actions')->name('chores.store');
    Route::get('chore-draws/{choreDraw}', [ChoreDrawController::class, 'show'])->whereNumber('choreDraw')->name('chores.show');
    Route::patch('chore-draws/{choreDraw}', [ChoreDrawController::class, 'update'])->whereNumber('choreDraw')->middleware('throttle:couple-actions')->name('chores.update');
    Route::get('mood/day', MoodDayController::class)->name('mood.day');
    Route::get('mood', [MoodController::class, 'index'])->name('mood.index');
    Route::post('mood', [MoodController::class, 'store'])->middleware('throttle:couple-actions')->name('mood.store');
    Route::post('mood/prompts', PartnerMoodPromptController::class)->middleware('throttle:couple-actions')->name('mood.prompts.store');
    Route::put('mood/{moodEntry}/emoji', [MoodController::class, 'emoji'])->whereNumber('moodEntry')->middleware('throttle:couple-actions')->name('mood.emoji');
    Route::put('mood/schedule', [MoodController::class, 'schedule'])->middleware('throttle:couple-actions')->name('mood.schedule');
    Route::get('reminders', [PartnerReminderController::class, 'index'])->name('reminders.index');
    Route::get('reminders/create', [PartnerReminderController::class, 'create'])->name('reminders.create');
    Route::post('reminders', [PartnerReminderController::class, 'store'])->middleware('throttle:couple-actions')->name('reminders.store');
    Route::delete('reminders/{partnerReminder}', [PartnerReminderController::class, 'destroy'])->whereNumber('partnerReminder')->middleware('throttle:couple-actions')->name('reminders.destroy');
});

Route::get('moods/{moodEntry}/sticker', MoodStickerController::class)->whereNumber('moodEntry')->middleware(['auth', 'auth.session'])->name('mood.sticker');
