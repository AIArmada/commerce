<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Botswana\BotswanaAddressFormatter;
use AIArmada\Addressing\Geography\Botswana\BotswanaGeographyProvider;

it('formats Botswanan addresses without a postcode system', function (): void {
    $formatted = app(BotswanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 231',
        'city' => 'HUKUNTSI',
        'country_code' => 'BW',
    ]));

    expect($formatted)->toBe("P.O. Box 231\nHUKUNTSI\nBotswana");
});
it('formats Botswanan private bag addresses with the town only', function (): void {
    $formatted = app(BotswanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P/Bag 1061',
        'city' => 'GABORONE',
        'country_code' => 'BW',
    ]));

    expect($formatted)->toBe("P/Bag 1061\nGABORONE\nBotswana");
});

it('ships Orapa as the fifth town', function (): void {
    $areas = app(BotswanaGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas->where('level', 1))->toHaveCount(17);

    $orapa = $areas->firstWhere('sourceId', 'bw:town:orapa');

    expect($orapa->name)->toBe('Orapa')
        ->and($orapa->type)->toBe('town')
        ->and($orapa->code)->toBe('OR');
});

it('ships 23 subdistricts under districts with parent links', function (): void {
    $areas = app(BotswanaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'subdistrict');

    expect($l2)->toHaveCount(23)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('bw:subdistrict:serowe')->name)->toBe('Serowe')
        ->and($byId->get('bw:subdistrict:palapye')->name)->toBe('Palapye')
        ->and($byId->get('bw:subdistrict:mochudi')->name)->toBe('Mochudi');
});
