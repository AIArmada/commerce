<?php

use Illuminate\Support\Facades\Route;
use AIArmada\CashierChip\Http\Controllers\WebhookController;

Route::post('/chip/webhook', WebhookController::class)->name('cashier-chip.webhook');
