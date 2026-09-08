<?php

declare(strict_types=1);

use AIArmada\Checkout\Webhooks\CheckoutSpatieSignatureValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\WebhookClient\WebhookConfigRepository;

it('does not register checkout signature middleware on webhook route', function (): void {
    $route = Route::getRoutes()->getByName('checkout.webhook.chip');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->not->toContain('AIArmada\\Checkout\\Http\\Middleware\\VerifyWebhookSignature');
});

it('validator allows webhook in non-production when checkout signature verification is disabled', function (): void {
    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/chip', 'POST', [
        'reference' => 'chk_' . (string) Str::uuid(),
        'status' => 'paid',
        'event_type' => 'purchase.paid',
    ]);
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook.chip');

    expect($config)->not->toBeNull()
        ->and($validator->isValid($request, $config))->toBeTrue();
});

it('validator rejects webhook in production when checkout signature verification is disabled', function (): void {
    $originalEnv = app()->environment();
    app()->bind('env', fn () => 'production');

    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/chip', 'POST', [
        'reference' => 'chk_' . (string) Str::uuid(),
        'status' => 'paid',
        'event_type' => 'purchase.paid',
    ]);
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook.chip');

    try {
        expect($config)->not->toBeNull()
            ->and($validator->isValid($request, $config))->toBeFalse();
    } finally {
        app()->bind('env', fn () => $originalEnv);
    }
});

it('rejects an unknown payload shape even when signature verification is disabled', function (): void {
    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/chip', 'POST', ['unexpected' => 'shape']);
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook.chip');

    expect($config)->not->toBeNull()
        ->and($validator->isValid($request, $config))->toBeFalse();
});

it('uses the webhook route name instead of payload shape to select the gateway', function (): void {
    config()->set('checkout.webhooks.verify_signature', false);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/stripe', 'POST', [
        'reference' => 'chk_' . (string) Str::uuid(),
        'status' => 'paid',
        'event_type' => 'purchase.paid',
    ]);
    $request->setRouteResolver(static fn () => Route::getRoutes()->getByName('checkout.webhook.stripe'));
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook.chip');

    expect($config)->not->toBeNull()
        ->and($validator->isValid($request, $config))->toBeFalse();
});

it('fails closed when the checkout-owned Stripe secret is missing', function (): void {
    config()->set('checkout.webhooks.verify_signature', true);
    config()->set('checkout.webhooks.stripe.secret', null);

    $validator = app(CheckoutSpatieSignatureValidator::class);
    $request = Request::create('/webhooks/stripe', 'POST', [
        'type' => 'checkout.session.completed',
        'data' => ['object' => []],
    ]);
    $config = app(WebhookConfigRepository::class)->getConfig('checkout.webhook.stripe');

    expect($config)->not->toBeNull()
        ->and($validator->isValid($request, $config))->toBeFalse();
});
