<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    $this->action = app(ImportAddressAreasAction::class);
});

it('imports valid hierarchy', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(1);
    expect($result->hasFailures())->toBeFalse();

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area)->not->toBeNull();
    expect($area->name)->toBe('Selangor');
    expect($area->slug)->toBe('selangor');
});

it('dry-run creates nothing', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $result = $this->action->execute($source, dryRun: true);

    expect($result->created)->toBe(0);
    expect($result->skipped)->toBe(1);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area)->toBeNull();
});

it('fails missing country', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'XX',
            type: 'state',
            name: 'Unknown',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('Country not found');
});

it('fails missing parent', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '2',
            countryCode: 'MY',
            type: 'district',
            name: 'Petaling',
            parentSourceId: 'nonexistent',
        ),
    ]);

    $result = $this->action->execute($source);

    expect($result->created)->toBe(0);
    expect($result->hasFailures())->toBeTrue();
    expect($result->failures[0]->reason)->toContain('Parent not found');
});

it('upserts by source and source_id', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
        ),
    ]);

    $this->action->execute($source);

    $source2 = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor Updated',
        ),
    ]);

    $result = $this->action->execute($source2);

    expect($result->created)->toBe(0);
    expect($result->updated)->toBe(1);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area->name)->toBe('Selangor Updated');
});

it('records source_payload and synced_at', function (): void {
    $source = new ArrayAddressAreaSource('test', [
        new AddressAreaData(
            source: 'test',
            sourceId: '1',
            countryCode: 'MY',
            type: 'state',
            name: 'Selangor',
            sourcePayload: ['external_id' => 'SGR-001'],
        ),
    ]);

    $this->action->execute($source);

    $area = AddressArea::where('source', 'test')->where('source_id', '1')->first();
    expect($area->source_payload)->toBe(['external_id' => 'SGR-001']);
    expect($area->synced_at)->not->toBeNull();
});
