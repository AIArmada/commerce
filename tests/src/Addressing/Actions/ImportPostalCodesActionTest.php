<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\ImportPostalCodesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\PostalCodeData;
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
