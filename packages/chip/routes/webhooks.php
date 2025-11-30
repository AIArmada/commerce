<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Controllers\WebhookController;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post(config('chip.webhooks.route', '/chip/webhook'), [WebhookController::class, 'handle'])
    ->middleware([VerifyWebhookSignature::class])
    ->name('chip.webhook');
