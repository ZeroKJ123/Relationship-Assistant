<?php

declare(strict_types=1);

use App\Http\Controllers\AppearanceAssetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Telegram\WebhookController;
use App\Http\Middleware\VerifyTelegramSecret;
use Illuminate\Support\Facades\Route;

Route::get('appearance-assets/{asset}', AppearanceAssetController::class)->where('asset', '[a-zA-Z0-9]{40}\\.(?:png|jpg|jpeg|webp)')->name('appearance.asset');

Route::get('/', DashboardController::class)->middleware('auth.session')->name('home');

require __DIR__.'/auth.php';

require __DIR__.'/couples.php';

require sprintf('%s/agreements.php', __DIR__);

require sprintf('%s/requests.php', __DIR__);

require sprintf('%s/duties.php', __DIR__);

require sprintf('%s/admin.php', __DIR__);

require sprintf('%s/catalogs.php', __DIR__);
require sprintf('%s/products.php', __DIR__);

require sprintf('%s/social.php', __DIR__);

require sprintf('%s/care.php', __DIR__);

Route::post('telegram/webhook', WebhookController::class)->middleware(VerifyTelegramSecret::class)->name('telegram.webhook');
