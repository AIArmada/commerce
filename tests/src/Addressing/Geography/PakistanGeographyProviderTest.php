<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Pakistan\PakistanAddressFormatter;
use AIArmada\Addressing\Geography\Pakistan\PakistanGeographyProvider;

it('formats Pakistani addresses with dash-separated postcodes', function (): void {
    $formatted = app(PakistanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'House No 17-B',
        'city' => 'ISLAMABAD',
        'postcode' => '44000',
        'country_code' => 'PK',
    ]));

    expect($formatted)->toBe("House No 17-B\nISLAMABAD-44000\nPakistan");
});

it('ships 178 districts with the May 2026 Balochistan batch', function (): void {
    $areas = app(PakistanGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas->where('level', 2))->toHaveCount(178)
        ->and($areas->where('parentSourceId', 'pk:province:balochistan'))->toHaveCount(42)
        ->and($areas->where('parentSourceId', 'pk:province:punjab'))->toHaveCount(41);

    $byId = $areas->keyBy->sourceId;

    expect($byId->has('pk:district:barshore'))->toBeTrue()
        ->and($byId->has('pk:district:quetta-east'))->toBeTrue()
        ->and($byId->has('pk:district:quetta-west'))->toBeTrue()
        ->and($byId->has('pk:district:tump'))->toBeTrue()
        ->and($byId->has('pk:district:wadh'))->toBeTrue()
        ->and($byId->has('pk:district:taftan'))->toBeTrue()
        ->and($byId->has('pk:district:upper-dera-bugti'))->toBeTrue()
        ->and($byId->has('pk:district:karezat'))->toBeFalse()
        ->and($byId->has('pk:district:quetta'))->toBeFalse()
        ->and($byId->has('pk:district:jampur'))->toBeFalse()
        ->and($byId->get('pk:district:kacchi')->name)->toBe('Kacchi')
        ->and($byId->get('pk:district:qila-abdullah')->name)->toBe('Qila Abdullah')
        ->and($byId->get('pk:district:battagram')->name)->toBe('Battagram')
        ->and($byId->get('pk:district:hattian-bala')->name)->toBe('Hattian Bala')
        ->and($byId->get('pk:district:neelam-valley')->name)->toBe('Neelam Valley')
        ->and($byId->get('pk:district:sudhanoti')->name)->toBe('Sudhanoti');
});
