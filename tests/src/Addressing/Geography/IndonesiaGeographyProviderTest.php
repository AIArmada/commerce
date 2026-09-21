<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
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

it('seeds the 38 ISO provinces including the six Papuas', function (): void {
    $country = $this->seedCountry('ID');

    app(IndonesiaGeographyProvider::class)->seed($country);

    $states = State::query()->where('country_id', $country->id)->orderBy('code')->pluck('name', 'code')->all();

    expect($states)->toHaveCount(38)
        ->and($states['JK'])->toBe('DKI Jakarta')
        ->and($states['YO'])->toBe('DI Yogyakarta')
        ->and($states['PA'])->toBe('Papua')
        ->and($states['PB'])->toBe('Papua Barat')
        ->and($states['PS'])->toBe('Papua Selatan')
        ->and($states['PT'])->toBe('Papua Tengah')
        ->and($states['PE'])->toBe('Papua Pegunungan')
        ->and($states['PD'])->toBe('Papua Barat Daya');
});

it('pins the verified regency, city, and district counts', function (): void {
    $areas = app(IndonesiaGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'province'))->toHaveCount(38)
        ->and($areas->where('type', 'regency'))->toHaveCount(416)
        ->and($areas->where('type', 'city'))->toHaveCount(98)
        ->and($areas->where('type', 'district'))->toHaveCount(7285);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('id:regency:3101')->type)->toBe('regency')
        ->and($byId->get('id:regency:3101')->parentSourceId)->toBe('id:province:31')
        ->and($byId->get('id:regency:3174')->type)->toBe('city')
        ->and($byId->get('id:regency:3174')->parentSourceId)->toBe('id:province:31')
        ->and($byId->get('id:district:110101')->parentSourceId)->toBe('id:regency:1101');
});

it('exposes village roles when the villages flag is on', function (): void {
    config()->set('addressing.geography.indonesia.villages', true);

    $roles = app(IndonesiaGeographyProvider::class)->areaRoles(new AddressCountry);

    expect($roles['id:village:1101012001'][0]['role'])->toBe('village')
        ->and($roles['id:urban_village:1201011001'][0]['role'])->toBe('village');
});
