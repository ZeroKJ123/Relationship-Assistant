<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

require __DIR__.'/auth.php';

require __DIR__.'/couples.php';

require sprintf('%s/agreements.php', __DIR__);
