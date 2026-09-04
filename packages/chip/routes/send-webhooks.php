<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\SendWebhookController;
use Illuminate\Support\Facades\Route;

Route::post(config('chip.webhooks.send.route', '/chip/send/webhooks'), [SendWebhookController::class, 'handle'])
    ->name('chip.send.webhook');
