<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\NewZealand\NewZealandGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 67 territorial authorities under regions with parent links', function (): void {
    $areas = app(NewZealandGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'district'))->toHaveCount(53)
        ->and($l2->where('type', 'city'))->toHaveCount(12)
        ->and($byId->get('nz:district:far-north')->parentSourceId)->toBe('nz:region:northland')
        ->and($byId->get('nz:district:waitaki')->parentSourceId)->toBe('nz:region:canterbury');
});

it('ships 1229 localities under their districts with postal locality roles', function (): void {
    $provider = app(NewZealandGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'locality'))->toHaveCount(1229)
        ->and($byId->get('nz:locality:summerhill')->parentSourceId)->toBe('nz:city:palmerston-north')
        ->and($byId->get('nz:locality:waioruarangi')->parentSourceId)->toBe('nz:district:kaikoura')
        ->and($byId->get('nz:locality:oamaru')->parentSourceId)->toBe('nz:district:waitaki');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['nz:locality:summerhill'][0]['role'])->toBe('postal_locality');
});

it('pins the B21 tree pass: 6 renames plus 28 homonym and suburb rows', function (): void {
    $areas = app(NewZealandGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas->get('nz:region:manawatu-whanganui')->name)->toBe('Manawatū-Whanganui')
        ->and($areas->get('nz:special_island_authority:chatham-islands')->name)->toBe('Chatham Islands Territory')
        ->and($areas->get('nz:locality:wanganui')->name)->toBe('Whanganui')
        ->and($areas->get('nz:locality:feilding')->name)->toBe('Feilding')
        ->and($areas->get('nz:locality:woodhill-whangarei')->parentSourceId)->toBe('nz:district:whangarei')
        ->and($areas->get('nz:locality:hillsborough-christchurch')->parentSourceId)->toBe('nz:city:christchurch')
        ->and($areas->get('nz:locality:richmond-tasman')->parentSourceId)->toBe('nz:district:tasman')
        ->and($areas->get('nz:locality:tora')->parentSourceId)->toBe('nz:district:south-wairarapa')
        ->and($areas->get('nz:locality:flat-bush')->parentSourceId)->toBe('nz:council:auckland')
        ->and($areas->get('nz:locality:chatham-islands')->parentSourceId)->toBe('nz:council:chatham-islands');
});

it('pins the B21 postal pass: 32 adds, 23 retargets, 4180 leg-less', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('NZ', $dir . '/new-zealand-postal-codes.csv', $dir . '/new-zealand-postal-code-areas.csv', 'aiarmada.addressing.new-zealand');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1769)
        ->and($postcodes)->toHaveCount(1770)
        ->and($byCode->get('4180')->first()->areaSourceId)->toBeNull();

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('0110'))->toBe('nz:locality:woodhill-whangarei')
        ->and($primary('2441'))->toBe('nz:locality:meremere-waikato')
        ->and($primary('4591'))->toBe('nz:locality:waverley-south-taranaki')
        ->and($primary('5782'))->toBe('nz:locality:tora')
        ->and($primary('7050'))->toBe('nz:locality:richmond-tasman')
        ->and($primary('9596'))->toBe('nz:locality:ngapuna-dunedin')
        ->and($primary('0118'))->toBe('nz:locality:one-tree-point')
        ->and($primary('2019'))->toBe('nz:locality:flat-bush')
        ->and($primary('3384'))->toBe('nz:locality:wairakei')
        ->and($primary('8016'))->toBe('nz:locality:chatham-islands')
        ->and($byCode->get('1081'))->toHaveCount(2);
});
