<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guam\GuamAddressFormatter;
use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;

it('formats Guamanian addresses with the US ZIP layout', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => '489 ARMY DR',
        'city' => 'BARRIGADA',
        'postcode' => '96913-9998',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("489 ARMY DR\nBARRIGADA GU 96913-9998\nGuam");
});
it('formats Hagatna addresses with its own ZIP', function (): void {
    $formatted = app(GuamAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Hagatna',
        'postcode' => '96910',
        'country_code' => 'GU',
    ]));

    expect($formatted)->toBe("PO Box 1\nHagatna GU 96910\nGuam");
});

it('leads renamed villages with the official Chamorro name', function (): void {
    $areas = app(GuamGeographyProvider::class)->addressAreaSource()->areas()->collect()->keyBy->sourceId;

    expect($areas)->toHaveCount(19)
        ->and($areas->get('gu:village:hagat')->name)->toBe('Hågat (Agat)')
        ->and($areas->get('gu:village:inarajan-inalahan')->name)->toBe('Inalåhan (Inarajan)')
        ->and($areas->get('gu:village:merizo-malesso')->name)->toBe('Malesso\' (Merizo)')
        ->and($areas->get('gu:village:santa-rita-santa-rita-sumai')->name)->toBe('Sånta Rita-Sumai (Santa Rita)')
        ->and($areas->get('gu:village:talofofo-talofofo')->name)->toBe('Talo\'fo\'fo (Talofofo)')
        ->and($areas->get('gu:village:umatac-humatak')->name)->toBe('Humåtak (Umatac)');
});
