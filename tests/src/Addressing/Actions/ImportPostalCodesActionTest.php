<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\PostalCodeData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\ArrayPostalCodeSource;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('imports postcode coverage and preserves source metadata', function (): void {
    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(
            source: 'areas',
            sourceId: 'locality-1',
            countryCode: 'MY',
            type: 'locality',
            name: 'Bangsar',
        ),
    ]));

    $result = app(ImportPostalCodesAction::class)->execute(new ArrayPostalCodeSource('postcodes', [
        new PostalCodeData(
            source: 'official-postcodes',
            sourceId: '50450',
            countryCode: 'MY',
            code: '50450',
            areaSource: 'areas',
            areaSourceId: 'locality-1',
        ),
    ]));

    $postcode = PostalCode::query()->where('code', '50450')->firstOrFail();

    expect($result->created)->toBe(1)
        ->and($result->hasFailures())->toBeFalse()
        ->and($postcode->metadata['source'])->toBe('official-postcodes')
        ->and($postcode->areas()->where('name', 'Bangsar')->exists())->toBeTrue();
});

it('fails instead of silently dropping missing postcode area links', function (): void {
    $result = app(ImportPostalCodesAction::class)->execute(new ArrayPostalCodeSource('postcodes', [
        new PostalCodeData(
            source: 'official-postcodes',
            sourceId: '99999',
            countryCode: 'MY',
            code: '99999',
            areaSource: 'areas',
            areaSourceId: 'missing',
        ),
    ]));

    expect($result->created)->toBe(0)
        ->and($result->hasFailures())->toBeTrue()
        ->and(PostalCode::query()->where('code', '99999')->exists())->toBeFalse();
});

it('moves source-owned postcode coverage when the source changes its area', function (): void {
    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(
            source: 'areas',
            sourceId: 'locality-1',
            countryCode: 'MY',
            type: 'locality',
            name: 'Bangsar',
        ),
        new AddressAreaData(
            source: 'areas',
            sourceId: 'locality-2',
            countryCode: 'MY',
            type: 'locality',
            name: 'Brickfields',
        ),
    ]));

    $source = static fn (string $area): ArrayPostalCodeSource => new ArrayPostalCodeSource('postcodes', [
        new PostalCodeData(
            source: 'official-postcodes',
            sourceId: '50450',
            countryCode: 'MY',
            code: '50450',
            areaSource: 'areas',
            areaSourceId: $area,
        ),
    ]);

    $firstResult = app(ImportPostalCodesAction::class)->execute($source('locality-1'));
    $secondResult = app(ImportPostalCodesAction::class)->execute($source('locality-2'));

    $postcode = PostalCode::query()->where('code', '50450')->firstOrFail();

    expect($firstResult->created)->toBe(1)
        ->and($secondResult->created)->toBe(0)
        ->and($secondResult->updated)->toBe(1)
        ->and($postcode->areas()->pluck('name')->all())->toBe(['Brickfields']);
});

it('scopes source coverage reconciliation to the postal code country', function (): void {
    $otherCountry = AddressCountry::query()->where('iso2', 'SG')->firstOrFail();
    $myArea = AddressArea::query()->create([
        'country_id' => AddressCountry::query()->where('iso2', 'MY')->value('id'),
        'country_code' => 'MY', 'type' => 'locality', 'name' => 'MY locality', 'slug' => 'my-locality',
        'source' => 'areas', 'source_id' => 'shared-area-my',
    ]);
    $sgArea = AddressArea::query()->create([
        'country_id' => $otherCountry->id,
        'country_code' => 'SG', 'type' => 'locality', 'name' => 'SG locality', 'slug' => 'sg-locality',
        'source' => 'areas', 'source_id' => 'shared-area-sg',
    ]);
    $source = static fn (string $country, string $areaId): ArrayPostalCodeSource => new ArrayPostalCodeSource('postcodes', [
        new PostalCodeData(source: 'shared-source', sourceId: 'shared-id', countryCode: $country, code: $country === 'MY' ? '50450' : '018989', areaSource: 'areas', areaSourceId: $areaId),
    ]);

    app(ImportPostalCodesAction::class)->execute($source('MY', 'shared-area-my'));
    app(ImportPostalCodesAction::class)->execute($source('SG', 'shared-area-sg'));

    expect(AddressAreaPostalCode::query()->where('address_area_id', $myArea->id)->exists())->toBeTrue()
        ->and(AddressAreaPostalCode::query()->where('address_area_id', $sgArea->id)->exists())->toBeTrue();
});

it('does not delete source-owned coverage for another country', function (): void {
    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(
            source: 'areas',
            sourceId: 'my-locality',
            countryCode: 'MY',
            type: 'locality',
            name: 'Bangsar',
        ),
        new AddressAreaData(
            source: 'areas',
            sourceId: 'sg-locality',
            countryCode: 'SG',
            type: 'locality',
            name: 'Orchard',
        ),
    ]));

    $source = static fn (string $countryCode, string $areaSourceId, string $code): ArrayPostalCodeSource => new ArrayPostalCodeSource('postcodes', [
        new PostalCodeData(
            source: 'official-postcodes',
            sourceId: 'shared-source-id',
            countryCode: $countryCode,
            code: $code,
            areaSource: 'areas',
            areaSourceId: $areaSourceId,
        ),
    ]);

    app(ImportPostalCodesAction::class)->execute($source('MY', 'my-locality', '50450'));
    app(ImportPostalCodesAction::class)->execute($source('SG', 'sg-locality', '238801'));

    expect(AddressArea::query()->where('name', 'Bangsar')->firstOrFail()->postalCodes()->exists())->toBeTrue()
        ->and(AddressArea::query()->where('name', 'Orchard')->firstOrFail()->postalCodes()->exists())->toBeTrue();
});
