<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\FormatAddressAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\State;

it('formats Malaysian addresses using the country formatter', function (): void {
    $address = AddressData::from([
        'line1' => 'Lot 12 Jalan Mawar',
        'city' => 'Kajang',
        'state' => 'Selangor',
        'postcode' => '43000',
        'countryCode' => 'MY',
        'components' => ['district' => 'Hulu Langat'],
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Lot 12 Jalan Mawar',
        'Hulu Langat',
        '43000 Kajang',
        'Selangor',
        'Malaysia',
    ]));
});

it('formats Singapore addresses with the postcode after the country', function (): void {
    $address = AddressData::from([
        'line1' => 'Blk 201 Farrer Park St 1',
        'line2' => '#20-102',
        'postcode' => '207226',
        'country' => 'Singapore',
        'countryCode' => 'SG',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Blk 201 Farrer Park St 1',
        '#20-102',
        'Singapore 207226',
    ]));
});

it('omits repeated Singapore city and state lines but keeps distinctive towns', function (): void {
    $address = AddressData::from([
        'line1' => '10 Bukit Batok Crescent',
        'city' => 'Singapore',
        'state' => 'Singapore',
        'postcode' => '658079',
        'countryCode' => 'SG',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        '10 Bukit Batok Crescent',
        'Singapore 658079',
    ]));

    $townAddress = AddressData::from([
        'line1' => '390 Tampines Ave 7',
        'city' => 'Tampines',
        'postcode' => '520390',
        'countryCode' => 'SG',
    ]);

    expect(app(FormatAddressAction::class)->format($townAddress))->toBe(implode("\n", [
        '390 Tampines Ave 7',
        'Tampines',
        'Singapore 520390',
    ]));
});

it('formats Indonesian addresses with the postcode after the city', function (): void {
    $address = AddressData::from([
        'line1' => 'Jl. Surya No. 10 RT 05/RW 02',
        'city' => 'Jakarta Pusat',
        'state' => 'DKI Jakarta',
        'postcode' => '10640',
        'country' => 'Indonesia',
        'countryCode' => 'ID',
        'components' => ['kelurahan' => 'Cempaka Baru', 'kecamatan' => 'Cempaka Putih'],
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Jl. Surya No. 10 RT 05/RW 02',
        'Cempaka Baru',
        'Cempaka Putih',
        'Jakarta Pusat 10640',
        'DKI Jakarta',
        'Indonesia',
    ]));
});

it('formats Brunei addresses with the town or district before the postcode', function (): void {
    $address = AddressData::from([
        'line1' => 'No. 7 Simpang 170, Jalan Muara',
        'city' => 'Muara',
        'state' => 'Brunei-Muara',
        'postcode' => 'BT2328',
        'country' => 'Brunei Darussalam',
        'countryCode' => 'BN',
        'components' => ['kampung' => 'Kampong Kapok'],
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'No. 7 Simpang 170, Jalan Muara',
        'Kampong Kapok',
        'Muara BT2328',
        'Brunei Darussalam',
    ]));

    $districtAddress = AddressData::from([
        'line1' => 'Pekan Bangar Lama',
        'state' => 'Temburong',
        'postcode' => 'PA1151',
        'countryCode' => 'BN',
    ]);

    expect(app(FormatAddressAction::class)->format($districtAddress))->toBe(implode("\n", [
        'Pekan Bangar Lama',
        'Temburong PA1151',
        'Brunei Darussalam',
    ]));
});

it('falls back to the generic formatter for countries without a formatter', function (): void {
    $address = AddressData::from([
        'line1' => 'Via della Conciliazione 1',
        'city' => 'Vatican City',
        'postcode' => '00120',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Via della Conciliazione 1',
        '00120 Vatican City',
        'VA',
    ]));
});

it('keeps zero street lines on the country formatter path', function (): void {
    $address = AddressData::from([
        'line1' => '0',
        'city' => 'Córdoba',
        'countryCode' => 'AR',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        '0',
        'Córdoba',
        'Argentina',
    ]));
});

it('keeps zero street and city lines on the generic path', function (): void {
    $address = AddressData::from([
        'line1' => '0',
        'city' => '0',
        'postcode' => '00120',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        '0',
        '00120 0',
        'VA',
    ]));
});

it('keeps sparse street lines on the generic path when line1 is missing', function (): void {
    $address = AddressData::from([
        'line2' => 'Unit marker two',
        'city' => 'City marker',
        'postcode' => '12345',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Unit marker two',
        '12345 City marker',
        'VA',
    ]));
});

it('keeps non-adjacent street lines on the generic path', function (): void {
    $address = AddressData::from([
        'line1' => 'First line',
        'line3' => 'Third line',
        'city' => 'City marker',
        'postcode' => '12345',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'First line',
        'Third line',
        '12345 City marker',
        'VA',
    ]));
});

it('prints the postcode on its own line when only a trailing street line exists', function (): void {
    $address = AddressData::from([
        'line3' => 'Third line only',
        'postcode' => '12345',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Third line only',
        '12345',
        'VA',
    ]));
});

it('keeps a zero street line on the generic path when line1 is missing', function (): void {
    $address = AddressData::from([
        'line2' => '0',
        'city' => 'City marker',
        'postcode' => '12345',
        'countryCode' => 'VA',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        '0',
        '12345 City marker',
        'VA',
    ]));
});

it('abbreviates full state names regardless of letter case', function (array $input, string $expected): void {
    expect(State::query()->count())->toBe(0);

    foreach (['title' => $input['state'], 'upper' => mb_strtoupper($input['state']), 'lower' => mb_strtolower($input['state'])] as $case => $variant) {
        $address = AddressData::from([...$input, 'state' => $variant]);

        expect(app(FormatAddressAction::class)->format($address))->toBe($expected, "Failed for {$case} [{$variant}].");
    }
})->with([
    'US California' => [
        ['line1' => '123 Main St', 'city' => 'Los Angeles', 'state' => 'California', 'postcode' => '90001', 'countryCode' => 'US'],
        "123 Main St\nLos Angeles CA 90001\nUnited States",
    ],
    'CA Ontario' => [
        ['line1' => '8450 Newman Blvd.', 'city' => 'Toronto', 'state' => 'Ontario', 'postcode' => 'M4B 1B3', 'countryCode' => 'CA'],
        "8450 Newman Blvd.\nToronto ON M4B 1B3\nCanada",
    ],
    'AU Victoria' => [
        ['line1' => '113 Bond St', 'city' => 'Melbourne', 'state' => 'Victoria', 'postcode' => '3000', 'countryCode' => 'AU'],
        "113 Bond St\nMelbourne  VIC  3000\nAustralia",
    ],
    'BR São Paulo' => [
        ['line1' => 'Av Paulista 1000', 'city' => 'São Paulo', 'state' => 'São Paulo', 'postcode' => '01310-100', 'countryCode' => 'BR'],
        "Av Paulista 1000\nSão Paulo - SP\n01310-100\nBrazil",
    ],
    'MX Jalisco' => [
        ['line1' => 'Av Juárez 100', 'city' => 'Guadalajara', 'state' => 'Jalisco', 'postcode' => '44100', 'countryCode' => 'MX'],
        "Av Juárez 100\n44100 Guadalajara, JAL\nMexico",
    ],
]);

it('prints the seeded country name for provider-less countries like Macao', function (): void {
    $this->seedCountry('MO');

    $address = AddressData::from([
        'line1' => 'Avenida da Praia Grande 762',
        'city' => 'Macao',
        'countryCode' => 'MO',
    ]);

    expect(app(FormatAddressAction::class)->format($address))->toBe(implode("\n", [
        'Avenida da Praia Grande 762',
        'Macao',
        'Macau S.A.R.',
    ]));
});
