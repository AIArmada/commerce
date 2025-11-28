<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cashier Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from payment gateways. Each gateway
| can have its own webhook endpoint for processing events.
|
*/

Route::middleware('api')->prefix('cashier')->name('cashier.')->group(function (): void {
    // Stripe webhooks (if using laravel/cashier, it will register its own routes)
    // Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('stripe.webhook');

    // CHIP webhooks (if using aiarmada/cashier-chip, it will register its own routes)
    // Route::post('chip/webhook', [ChipWebhookController::class, 'handleWebhook'])->name('chip.webhook');
});
