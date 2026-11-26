<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\RequestCache;
use AIArmada\Addressing\Support\SchemaTableCache;
use Illuminate\Support\Facades\DB;

it('separates request lookups by configured connection and table', function (): void {
    $default = DB::getDefaultConnection();
    config()->set('database.connections.addressing_cache_test', config("database.connections.{$default}"));
    $first = new AddressCountry;
    $second = (new AddressCountry)->setConnection('addressing_cache_test');

    try {
        expect(RequestCache::remember('test', $first, 'MY', static fn (): string => 'first'))->toBe('first')
            ->and(RequestCache::remember('test', $second, 'MY', static fn (): string => 'second'))->toBe('second');

        RequestCache::forget('test');

        expect(RequestCache::remember('test', $first, 'MY', static fn (): string => 'refreshed'))->toBe('refreshed');
    } finally {
        DB::purge('addressing_cache_test');
    }
});

it('checks table availability using the model connection', function (): void {
    $default = DB::getDefaultConnection();
    $configuration = config("database.connections.{$default}");
    $configuration['prefix'] = 'addressing_cache_absent_';
    config()->set('database.connections.addressing_cache_test', $configuration);

    try {
        expect(SchemaTableCache::exists(new AddressCountry))->toBeTrue()
            ->and(SchemaTableCache::exists((new AddressCountry)->setConnection('addressing_cache_test')))->toBeFalse();
    } finally {
        DB::purge('addressing_cache_test');
    }
});
