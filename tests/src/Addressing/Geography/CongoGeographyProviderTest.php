<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Congo\CongoAddressFormatter;
use AIArmada\Addressing\Geography\Congo\CongoGeographyProvider;

it('formats Congolese addresses without a postcode system', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\nCongo");
});
it('prints any supplied Congolese code on its own line', function (): void {
    $formatted = app(CongoAddressFormatter::class)->format(AddressData::from([
        'line1' => '12, rue Kakamoueka',
        'city' => 'BRAZZAVILLE',
        'postcode' => '99999',
        'country_code' => 'CG',
    ]));

    expect($formatted)->toBe("12, rue Kakamoueka\nBRAZZAVILLE\n99999\nCongo");
});

it('ships the 15 departments including the October 2024 trio', function (): void {
    $areas = app(CongoGeographyProvider::class)->addressAreaSource()->areas();

    expect($areas)->toHaveCount(15);

    $byId = $areas->keyBy('sourceId');

    expect($byId->get('cg:department:congo-oubangui')->name)->toBe('Congo-Oubangui')
        ->and($byId->get('cg:department:congo-oubangui')->code)->toBe('17')
        ->and($byId->get('cg:department:djoue-lefini')->name)->toBe('Djoué-Léfini')
        ->and($byId->get('cg:department:djoue-lefini')->code)->toBe('18')
        ->and($byId->get('cg:department:nkeni-alima')->name)->toBe('Nkéni-Alima')
        ->and($byId->get('cg:department:nkeni-alima')->code)->toBe('19');
});
