<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Brazil\BrazilAddressFormatter;
use AIArmada\Addressing\Geography\Brazil\BrazilGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Brazilian addresses with the state abbreviation and postcode below', function (): void {
    $formatted = app(BrazilAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RUA XV DE NOVEMBRO, 1751',
        'city' => 'GUARAPUAVA',
        'state' => 'Paraná',
        'postcode' => '85070-200',
        'country_code' => 'BR',
    ]));

    expect($formatted)->toBe("RUA XV DE NOVEMBRO, 1751\nGUARAPUAVA - PR\n85070-200\nBrazil");
});

it('ships 5,571 municipalities under their states', function (): void {
    $areas = app(BrazilGeographyProvider::class)->addressAreaSource()->areas()->collect();

    expect($areas)->toHaveCount(5598)
        ->and($areas->where('level', 1))->toHaveCount(27)
        ->and($areas->where('level', 2))->toHaveCount(5571)
        ->and($areas->where('parentSourceId', 'br:state:minas-gerais'))->toHaveCount(853)
        ->and($areas->where('parentSourceId', 'br:state:sao-paulo'))->toHaveCount(645)
        ->and($areas->where('parentSourceId', 'br:state:mato-grosso'))->toHaveCount(142);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('br:municipality:3550308')->name)->toBe('São Paulo')
        ->and($byId->get('br:municipality:5101837')->name)->toBe('Boa Esperança do Norte')
        ->and($byId->get('br:municipality:5300108')->name)->toBe('Brasília')
        ->and($byId->get('br:district:2605459')->name)->toBe('Fernando de Noronha');
});

it('labels tiers with Portuguese administrative terms', function (): void {
    $provider = app(BrazilGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['state' => 'Estado', 'federal_district' => 'Distrito Federal', 'municipality' => 'Município', 'district' => 'Distrito'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});

it('declares UF abbreviations for all 27 states and the federal district', function (): void {
    $names = app(BrazilGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(27)
        ->and($names['br:state:sao-paulo'][0])->toBe(['name' => 'SP', 'name_type' => 'abbreviation'])
        ->and($names['br:federal_district:distrito-federal'][0]['name'])->toBe('DF');
});
