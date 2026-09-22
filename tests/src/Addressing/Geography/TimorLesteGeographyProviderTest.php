<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteAddressFormatter;
use AIArmada\Addressing\Geography\TimorLeste\TimorLesteGeographyProvider;

it('formats Timorese addresses with the postcode right of the municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'AVENIDA CAPITA SINMAU',
        'state' => 'AINARO',
        'postcode' => 'TL42000',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("AVENIDA CAPITA SINMAU\nAINARO TL42000\nTimor-Leste");
});
it('formats Timorese addresses joining distinct city and municipality', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'TRAVESSA LAVANDARIA NO.12',
        'city' => 'Bairo Pite',
        'state' => 'DILI',
        'postcode' => 'TL11212',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("TRAVESSA LAVANDARIA NO.12\nBairo Pite - DILI TL11212\nTimor-Leste");
});
it('prints Timorese city-municipalities once when city and state match', function (): void {
    $formatted = app(TimorLesteAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Avenida Presidente Nicolau Lobato',
        'city' => 'DILI',
        'state' => 'DILI',
        'postcode' => 'TL10901',
        'country_code' => 'TL',
    ]));

    expect($formatted)->toBe("Avenida Presidente Nicolau Lobato\nDILI TL10901\nTimor-Leste");
});

it('ships 67 administrative_posts under municipalitys with parent links', function (): void {
    $areas = app(TimorLesteGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'administrative_post');

    expect($l2)->toHaveCount(67)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('tl:administrative_post:cristo-rei')->name)->toBe('Cristo Rei')
        ->and($byId->get('tl:administrative_post:pante-macassar')->name)->toBe('Pante Macassar')
        ->and($byId->get('tl:administrative_post:quelicai-antiga')->name)->toBe('Quelicai Antiga');
});
