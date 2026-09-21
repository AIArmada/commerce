<?php

declare(strict_types=1);

use AIArmada\Chip\ChipServiceProvider;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Chip\Models\Webhook;
use Illuminate\Support\Facades\Route;

describe('Package bootstrap', function (): void {
    it('configures the CHIP webhook row through its Webhook subclass', function (): void {
        $chipConfig = collect(config('webhook-client.configs'))
            ->firstWhere('name', Webhook::WEBHOOK_NAME);

        expect($chipConfig)->toBeArray()
            ->and($chipConfig['webhook_model'])->toBe(Webhook::class);
    });

    it('registers the package webhook route without signature middleware', function (): void {
        $route = Route::getRoutes()->getByName('chip.webhook');

        expect($route)->not->toBeNull();
        expect($route?->uri())->toBe(mb_ltrim((string) config('chip.webhooks.route'), '/'));
        expect($route?->gatherMiddleware())->not->toContain(VerifyWebhookSignature::class);
    });

    it('preserves unrelated webhook-client configs when registering chip webhook config', function (): void {
        config()->set('webhook-client.configs', [
            [
                'name' => 'existing.webhook',
                'signature_header_name' => 'x-existing-signature',
            ],
        ]);

        $provider = new ChipServiceProvider(app());

        $method = new ReflectionMethod($provider, 'configureSpatieWebhookClient');
        $method->setAccessible(true);
        $method->invoke($provider);

        $configs = config('webhook-client.configs');

        expect($configs)->toBeArray()
            ->and(collect($configs)->pluck('name')->all())
            ->toContain('existing.webhook', 'chip.webhook');
    });
});
