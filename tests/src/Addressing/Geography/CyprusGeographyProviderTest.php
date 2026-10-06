<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Cyprus\CyprusGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 755 localities under their districts with postal locality roles', function (): void {
    $provider = app(CyprusGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'locality'))->toHaveCount(755)
        ->and($byId->get('cy:locality:paralimni')->parentSourceId)->toBe('cy:district:famagusta-magusa')
        // B20 P1/P5 adds + P2 dedup + R1-R5 renames.
        ->and($byId->get('cy:locality:ammochostos')->parentSourceId)->toBe('cy:district:famagusta-magusa')
        ->and($byId->get('cy:locality:agios-fotios')->parentSourceId)->toBe('cy:district:paphos-pafos')
        ->and($byId->get('cy:locality:ampelitis')->parentSourceId)->toBe('cy:district:paphos-pafos')
        ->and($byId->get('cy:locality:lefkosia-omorfita')->name)->toBe('Lefkosia (Omorfita)')
        ->and($byId->has('cy:locality:agios-georgios-acheritou'))->toBeFalse()
        ->and($byId->get('cy:locality:kato-zodeia')->name)->toBe('Kato Zodeia')
        ->and($byId->get('cy:locality:pano-zodeia')->name)->toBe('Pano Zodeia')
        ->and($byId->get('cy:locality:tremetousia')->name)->toBe('Tremetousia')
        ->and($byId->get('cy:locality:komi-kebir')->name)->toBe('Komi Kebir')
        ->and($byId->get('cy:locality:tziaos')->name)->toBe('Tziaos');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['cy:locality:paralimni'][0]['role'])->toBe('postal_locality');
});

it('pins the B20 directory fixes: 8 added codes, 5720 dropped, Fylousa swap, 4528 flip', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('CY', $dir . '/cyprus-postal-codes.csv', $dir . '/cyprus-postal-code-areas.csv', 'aiarmada.addressing.cyprus');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    expect($byCode)->toHaveCount(1132)
        ->and($postcodes)->toHaveCount(1135);

    $primary = fn (string $code) => $byCode->get($code)->where('isPrimary', true)->first()->areaSourceId;

    expect($primary('1000'))->toBe('cy:locality:lefkosia')
        ->and($primary('5000'))->toBe('cy:locality:ammochostos')
        ->and($primary('8652'))->toBe('cy:locality:agios-fotios')
        ->and($primary('8653'))->toBe('cy:locality:ampelitis')
        ->and($byCode->has('5720'))->toBeFalse()
        ->and($primary('5520'))->toBe('cy:locality:agios-georgios-ammochostou')
        ->and($primary('8629'))->toBe('cy:locality:fylousa-kelokedaron')
        ->and($primary('8811'))->toBe('cy:locality:fylousa-chrysochous')
        ->and($primary('4528'))->toBe('cy:locality:pentakomo')
        ->and($byCode->get('1025')->pluck('areaSourceId')->contains('cy:locality:lefkosia-omorfita'))->toBeTrue()
        ->and($primary('2723'))->toBe('cy:locality:kato-zodeia')
        ->and($primary('5828'))->toBe('cy:locality:komi-kebir')
        ->and($primary('5654'))->toBe('cy:locality:tziaos');
});
