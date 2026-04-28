<?php

declare(strict_types=1);

use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

describe('Package bootstrap', function (): void {
    it('binds collect, send and webhook services', function (): void {
        expect(app()->bound('chip.collect'))->toBeTrue();
        expect(app()->bound('chip.send'))->toBeTrue();
        expect(app()->bound('chip.webhook'))->toBeTrue();
    });

    it('migrates package tables', function (): void {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        expect(Schema::hasTable($tablePrefix . 'purchases'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'payments'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'webhooks'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'send_instructions'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'bank_accounts'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'clients'))->toBeTrue();
    });

    it('loads configuration from chip config file', function (): void {
        expect(config('chip.collect.api_key'))->toBe('test_secret_key');
        expect(config('chip.send.api_key'))->toBe('test_api_key');
        expect(config('chip.environment'))->toBe('sandbox');
        expect(config('chip.webhooks.company_public_key'))->toBe('test_public_key');
        expect(config('chip.webhooks.store_webhooks'))->toBeTrue();
    });

    it('registers the package webhook route without signature middleware', function (): void {
        $route = Route::getRoutes()->getByName('chip.webhook');

        expect($route)->not->toBeNull();
        expect($route?->uri())->toBe(mb_ltrim((string) config('chip.webhooks.route'), '/'));
        expect($route?->gatherMiddleware())->not->toContain(VerifyWebhookSignature::class);
    });
});
