<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Indonesia\IndonesiaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\CompositeAddressAreaSource;
use AIArmada\Addressing\Support\CsvAddressAreaSource;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

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

it('links deep areas to their province ancestor', function (): void {
    $relationships = app(IndonesiaGeographyProvider::class)->areaRelationships(new AddressCountry);

    expect($relationships['id:district:110101'])->toHaveCount(2)
        ->and($relationships['id:district:110101'][0]['parent_source_id'])->toBe('id:regency:1101')
        ->and($relationships['id:district:110101'][1]['parent_source_id'])->toBe('id:province:11')
        ->and($relationships['id:regency:1101'])->toHaveCount(1)
        ->and($relationships['id:regency:1101'][0]['parent_source_id'])->toBe('id:province:11');
});

it('uses the official Kepmendagri 2025 names for the corrected regencies', function (): void {
    $byId = app(IndonesiaGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    // Upstream region-id mangled these with neighbour/province tokens or
    // truncations; verified against Kepmendagri 300.2.2-2138/2025.
    expect($byId->get('id:regency:1172')->name)->toBe('Kota Sabang')
        ->and($byId->get('id:regency:1217')->name)->toBe('Kabupaten Samosir')
        ->and($byId->get('id:regency:1218')->name)->toBe('Kabupaten Serdang Bedagai')
        ->and($byId->get('id:regency:1307')->name)->toBe('Kabupaten Lima Puluh Kota')
        ->and($byId->get('id:regency:1612')->name)->toBe('Kabupaten Penukal Abab Lematang Ilir')
        ->and($byId->get('id:regency:3276')->name)->toBe('Kota Depok')
        ->and($byId->get('id:regency:3514')->name)->toBe('Kabupaten Pasuruan')
        ->and($byId->get('id:regency:6408')->name)->toBe('Kabupaten Kutai Timur')
        ->and($byId->get('id:regency:6474')->name)->toBe('Kota Bontang')
        ->and($byId->get('id:regency:7109')->name)->toBe('Kabupaten Kepulauan Siau Tagulandang Biaro')
        ->and($byId->get('id:regency:7310')->name)->toBe('Kabupaten Pangkajene dan Kepulauan')
        ->and($byId->get('id:regency:7324')->name)->toBe('Kabupaten Luwu Timur')
        ->and($byId->get('id:regency:7601')->name)->toBe('Kabupaten Pasangkayu')
        ->and($byId->get('id:regency:8201')->name)->toBe('Kabupaten Halmahera Barat')
        ->and($byId->get('id:regency:9401')->name)->toBe('Kabupaten Nabire');
});

it('links postcodes to the re-verified regencies', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('ID', $dir . '/indonesia-postal-codes.csv', $dir . '/indonesia-postal-code-areas.csv', 'aiarmada.addressing.indonesia');

    $postcodes = $source->postalCodes()->collect();

    expect($postcodes)->toHaveCount(9359)
        ->and($postcodes->every(static fn ($row): bool => (bool) preg_match('/^\\d{5}$/', (string) $row->code)))->toBeTrue()
        ->and($postcodes->every(static fn ($row): bool => $row->isPrimary))->toBeTrue();

    $byCode = $postcodes->keyBy->code;

    // Corrected systematic rotations: Jakarta prefixes, Medan/Binjai,
    // Bukittinggi, OKU Timur, Wondama, Nabire, Merauke, Tual.
    expect((string) $byCode->get('10110')->areaSourceId)->toBe('id:regency:3171')
        ->and((string) $byCode->get('11110')->areaSourceId)->toBe('id:regency:3173')
        ->and((string) $byCode->get('12110')->areaSourceId)->toBe('id:regency:3174')
        ->and((string) $byCode->get('13110')->areaSourceId)->toBe('id:regency:3175')
        ->and((string) $byCode->get('14110')->areaSourceId)->toBe('id:regency:3172')
        ->and((string) $byCode->get('20241')->areaSourceId)->toBe('id:regency:1271')
        ->and((string) $byCode->get('20352')->areaSourceId)->toBe('id:regency:1207')
        ->and((string) $byCode->get('20711')->areaSourceId)->toBe('id:regency:1275')
        ->and((string) $byCode->get('20762')->areaSourceId)->toBe('id:regency:1205')
        ->and((string) $byCode->get('26111')->areaSourceId)->toBe('id:regency:1375')
        ->and((string) $byCode->get('32311')->areaSourceId)->toBe('id:regency:1608')
        ->and((string) $byCode->get('98362')->areaSourceId)->toBe('id:regency:9207')
        ->and((string) $byCode->get('98811')->areaSourceId)->toBe('id:regency:9401')
        ->and((string) $byCode->get('99611')->areaSourceId)->toBe('id:regency:9301')
        ->and((string) $byCode->get('97611')->areaSourceId)->toBe('id:regency:8172')
        ->and((string) $byCode->get('60111')->areaSourceId)->toBe('id:regency:3578')
        ->and((string) $byCode->get('50111')->areaSourceId)->toBe('id:regency:3374')
        ->and((string) $byCode->get('20111')->areaSourceId)->toBe('id:regency:1271')
        ->and((string) $byCode->get('90111')->areaSourceId)->toBe('id:regency:7371')
        ->and((string) $byCode->get('57111')->areaSourceId)->toBe('id:regency:3372')
        ->and((string) $byCode->get('63111')->areaSourceId)->toBe('id:regency:3577')
        ->and((string) $byCode->get('75111')->areaSourceId)->toBe('id:regency:6472')
        ->and((string) $byCode->get('78611')->areaSourceId)->toBe('id:regency:6105');

    // Oct-2026 open-log #1 retry: official Pos Indonesia directory +
    // second signals. Six relinks off stale GN-family vintage, two drops.
    expect((string) $byCode->get('99674')->areaSourceId)->toBe('id:regency:9302')
        ->and((string) $byCode->get('20524')->areaSourceId)->toBe('id:regency:1271')
        ->and((string) $byCode->get('20525')->areaSourceId)->toBe('id:regency:1271')
        ->and((string) $byCode->get('92661')->areaSourceId)->toBe('id:regency:7307')
        ->and((string) $byCode->get('98865')->areaSourceId)->toBe('id:regency:9406')
        ->and((string) $byCode->get('84111')->areaSourceId)->toBe('id:regency:5272')
        ->and($byCode->has('52191'))->toBeFalse()
        ->and($byCode->has('34663'))->toBeFalse();
});
