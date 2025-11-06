<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

describe('Package bootstrap', function (): void {
    it('binds collect, send and webhook services', function (): void {
        expect(app()->bound('chip.collect'))->toBeTrue();
        expect(app()->bound('chip.send'))->toBeTrue();
        expect(app()->bound('chip.webhook'))->toBeTrue();
    });

    it('migrates package tables', function (): void {
        $tablePrefix = config('chip.database.table_prefix', 'chip_');

        expect(Schema::hasTable($tablePrefix.'purchases'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix.'payments'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix.'webhooks'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix.'send_instructions'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix.'bank_accounts'))->toBeTrue();
        expect(Schema::hasTable($tablePrefix.'clients'))->toBeTrue();
    });

    it('loads configuration from chip config file', function (): void {
        expect(config('chip.collect.secret_key'))->toBe('test_secret_key');
        expect(config('chip.send.api_key'))->toBe('test_api_key');
        expect(config('chip.is_sandbox'))->toBeTrue();
    });
});
