<?php

declare(strict_types=1);

use AIArmada\Checkout\Webhooks\CheckoutSpatieSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\WebhookConfigRepository;

it('does not register checkout signature middleware on webhook route', function (): void {
    $route = Route::getRoutes()->getByName('checkout.webhook');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->not->toContain('AIArmada\\Checkout\\Http\\Middleware\\VerifyWebhookSignature');
});

it('validator allows webhook in non-production when checkout signature verification is disabled', function (): void {
    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/checkout', 'POST');
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook');

    expect($config)->not->toBeNull()
        ->and($validator->isValid($request, $config))->toBeTrue();
});

it('validator rejects webhook in production when checkout signature verification is disabled', function (): void {
    $originalEnv = app()->environment();
    app()->bind('env', fn () => 'production');

    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/checkout', 'POST');
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook');

    try {
        expect($config)->not->toBeNull()
            ->and($validator->isValid($request, $config))->toBeFalse();
    } finally {
        app()->bind('env', fn () => $originalEnv);
    }
});
