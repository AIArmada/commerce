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

    expect($areas)->toHaveCount(17);

    $orapa = $areas->firstWhere('sourceId', 'bw:town:orapa');

    expect($orapa->name)->toBe('Orapa')
        ->and($orapa->type)->toBe('town')
        ->and($orapa->code)->toBe('OR');
});
