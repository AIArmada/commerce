<?php

declare(strict_types=1);

use AIArmada\CashierChip\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/chip/webhook', WebhookController::class)->name('cashier-chip.webhook');
