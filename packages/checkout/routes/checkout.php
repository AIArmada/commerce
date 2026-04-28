<?php

declare(strict_types=1);

use AIArmada\Checkout\Http\Controllers\CheckoutWebhookController;
use AIArmada\Checkout\Http\Controllers\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Checkout Routes
|--------------------------------------------------------------------------
|
| These routes handle payment callbacks and webhooks for the checkout flow.
| The callback routes handle user redirects from payment gateways.
| The webhook route handles async payment notifications.
|
| All route paths are configurable via checkout.routes config.
|
*/

Route::prefix(config('checkout.routes.prefix', 'checkout'))
    ->middleware(config('checkout.routes.middleware', ['web']))
    ->group(function (): void {
        // Payment callback routes (user redirects from gateway)
        Route::get(config('checkout.routes.callbacks.success', 'payment/success'), [PaymentCallbackController::class, 'success'])
            ->name('checkout.payment.success');

        Route::get(config('checkout.routes.callbacks.failure', 'payment/failure'), [PaymentCallbackController::class, 'failure'])
            ->name('checkout.payment.failure');

        Route::get(config('checkout.routes.callbacks.cancel', 'payment/cancel'), [PaymentCallbackController::class, 'cancel'])
            ->name('checkout.payment.cancel');
    });

// Webhook route (uses different middleware - no CSRF, no session)
// Signature verification is enforced via checkout Spatie signature validator.
Route::prefix(config('checkout.routes.webhook_prefix', 'webhooks'))
    ->middleware(config('checkout.routes.webhook_middleware', ['api']))
    ->group(function (): void {
        Route::post(config('checkout.routes.webhook_path', 'checkout'), CheckoutWebhookController::class)
            ->name('checkout.webhook');
    });
