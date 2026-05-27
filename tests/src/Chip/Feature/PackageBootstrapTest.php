<?php

declare(strict_types=1);

use AIArmada\Chip\ChipServiceProvider;
use AIArmada\Chip\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Database\Migrations\Migration;
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
        expect(Schema::hasTable('webhook_calls'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'send_instructions'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'bank_accounts'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix . 'clients'))->toBeTrue();
    });

    it('can rerun the chip webhook extension without duplicate indexes', function (): void {
        /** @var Migration $migration */
        $migration = require dirname(__DIR__, 4) . '/packages/chip/database/migrations/2000_04_01_000003_add_chip_webhook_columns_to_webhook_calls_table.php';

        $migration->up();
        $migration->up();

        expect(Schema::hasColumn('webhook_calls', 'event_type'))->toBeTrue()
            ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_event_type_processed_idx'))->toBeTrue()
            ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_verified_processed_idx'))->toBeTrue()
            ->and(Schema::hasIndex('webhook_calls', 'webhook_calls_status_retry_count_idx'))->toBeTrue();
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
