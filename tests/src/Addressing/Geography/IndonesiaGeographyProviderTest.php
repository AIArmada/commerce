<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Support\CompositeAddressAreaSource;
use AIArmada\Addressing\Support\CsvAddressAreaSource;

it('uses only the main source when the villages flag is off', function (): void {
    expect(app(IndonesiaGeographyProvider::class)->addressAreaSource())->toBeInstanceOf(CsvAddressAreaSource::class);
});

it('streams the opt-in villages when the flag is on', function (): void {
    config()->set('addressing.geography.indonesia.villages', true);

    $source = app(IndonesiaGeographyProvider::class)->addressAreaSource();

    expect($source)->toBeInstanceOf(CompositeAddressAreaSource::class);

    $first = $source->areas()->firstWhere('sourceId', 'id:village:1101012001');

    expect($first->name)->toBe('Keude Bakongan')
        ->and($first->type)->toBe('village')
        ->and($first->code)->toBe('1101012001')
        ->and($first->parentSourceId)->toBe('id:district:110101')
        ->and($first->level)->toBe(4)
        ->and($first->source)->toBe(IndonesiaGeographyProvider::AREA_SOURCE);
});
