<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Japan\JapanAddressFormatter;
use AIArmada\Addressing\Geography\Japan\JapanGeographyProvider;

it('formats Japanese addresses with the postcode sharing the country line', function (): void {
    $formatted = app(JapanAddressFormatter::class)->format(AddressData::from([
        'line1' => '10-23, Mitsugi 1-chome',
        'city' => 'Musashi-Murayama-shi',
        'state' => 'TOKYO',
        'postcode' => '231-0012',
        'country_code' => 'JP',
    ]));

    expect($formatted)->toBe("10-23, Mitsugi 1-chome\nMusashi-Murayama-shi, TOKYO\n231-0012 Japan");
});

it('prints country alone when the postcode is missing', function (): void {
    $formatted = app(JapanAddressFormatter::class)->format(AddressData::from([
        'line1' => '4-3-2, Hakusan',
        'city' => 'Bunkyo-ku',
        'state' => 'TOKYO',
        'country_code' => 'JP',
    ]));

    expect($formatted)->toBe("4-3-2, Hakusan\nBunkyo-ku, TOKYO\nJapan");
});

it('types municipalities by kind from the kanji suffix', function (): void {
    $areas = app(JapanGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'city'))->toHaveCount(792)
        ->and($areas->where('type', 'town'))->toHaveCount(743)
        ->and($areas->where('type', 'village'))->toHaveCount(189)
        ->and($areas->where('type', 'ward'))->toHaveCount(23)
        ->and($byId->get('jp:municipality:13101')->type)->toBe('ward')
        ->and($byId->get('jp:municipality:10523')->type)->toBe('town')
        ->and($byId->get('jp:municipality:01695')->type)->toBe('village');
});
