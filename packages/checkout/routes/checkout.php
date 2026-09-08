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
| Callback routes are gateway-specific so the URL selects the gateway contract.
| Webhook routes are also gateway-specific for signature verification.
|
| All route paths are configurable via checkout.routes config.
|
*/

Route::prefix(config('checkout.routes.prefix', 'checkout'))
    ->middleware(config('checkout.routes.middleware', ['web']))
    ->group(function (): void {
        foreach (config('checkout.routes.callbacks', []) as $type => $gatewayRoutes) {
            if (! is_array($gatewayRoutes)) {
                continue;
            }

            foreach ($gatewayRoutes as $gateway => $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }

                Route::get($path, [PaymentCallbackController::class, $type])
                    ->name("checkout.payment.{$gateway}.{$type}");
            }
        }
    });

Route::prefix(config('checkout.routes.webhook_prefix', 'webhooks'))
    ->middleware(config('checkout.routes.webhook_middleware', ['api']))
    ->group(function (): void {
        foreach (config('checkout.routes.webhooks', []) as $webhook) {
            if (! is_array($webhook)) {
                continue;
            }

            $path = $webhook['path'] ?? null;
            $name = $webhook['config'] ?? null;

            if (! is_string($path) || $path === '' || ! is_string($name) || $name === '') {
                continue;
            }

            Route::post($path, CheckoutWebhookController::class)->name($name);
        }
    });
