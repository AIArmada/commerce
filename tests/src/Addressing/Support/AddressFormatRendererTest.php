<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Support\AddressFormatRenderer;

it('renders the MY ruled format: postcode joined with town-or-daerah partner', function (): void {
    // Ruled 2026-10-08: MY has no city level. The postcode line joins the
    // writer-computed partner (town area name when the subdivision is
    // town-like, else the daerah). Locality text stays in the street lines
    // as written; joins use ", ", never a trailing comma.
    $rural = AddressFormatRenderer::format('MY', AddressData::from([
        'line1' => 'Pt Hj Ibrahim, Bagan Mukim 2',
        'components' => ['postcode_partner' => 'Batu Pahat'],
        'state' => 'Johor',
        'postcode' => '83000',
        'countryCode' => 'MY',
    ]));

    $urban = AddressFormatRenderer::format('MY', AddressData::from([
        'line1' => '2, Jalan Gunung Nuang U11/9D',
        'line2' => 'Taman Bukit Bandaraya, Seksyen U11',
        'components' => ['postcode_partner' => 'Shah Alam'],
        'state' => 'Selangor',
        'postcode' => '40170',
        'countryCode' => 'MY',
    ]));

    expect($rural)->toBe("Pt Hj Ibrahim, Bagan Mukim 2\n83000, Batu Pahat\nJohor\nMalaysia")
        ->and($urban)->toBe("2, Jalan Gunung Nuang U11/9D\nTaman Bukit Bandaraya, Seksyen U11\n40170, Shah Alam\nSelangor\nMalaysia");
});

it('renders the 2026-10-09 sweep rulings: UPU-sheet examples per changed country', function (string $code, array $input, string $expected): void {
    // Full 228-country sweep (UPU mirror sheets + Chromium + national post).
    // Each case is a UPU-sheet / national-post example from the shard reports.
    expect(AddressFormatRenderer::format($code, AddressData::from($input)))->toBe($expected);
})->with([
    // AF — UPU AFG 07/2025: urban `100208 KABUL`, rural `106401 PAGHMAN / KABUL`.
    ['AF', ['city' => 'Kabul', 'state' => 'Kabul', 'postcode' => '100208', 'country' => 'Afghanistan'], "100208 Kabul\nAfghanistan"],
    ['AF', ['city' => 'Paghman', 'state' => 'Kabul', 'postcode' => '106401', 'country' => 'Afghanistan'], "106401 Paghman\nKabul\nAfghanistan"],
    // BS — UPU BHS 10/2025: `NASSAU, N.P.`.
    ['BS', ['city' => 'Nassau', 'state' => 'New Providence', 'country' => 'The Bahamas'], "Nassau, New Providence\nThe Bahamas"],
    // CR — UPU CRI: province, canton, district comma-joined, postcode own line.
    ['CR', ['city' => 'San Rafael, San Rafael', 'state' => 'Heredia', 'postcode' => '40501', 'country' => 'Costa Rica'], "Heredia, San Rafael, San Rafael\n40501\nCosta Rica"],
    // CU — UPU CUB: `CP 10600 CIUDAD HABANA` (CP baked in: values are bare digits).
    ['CU', ['city' => 'Ciudad Habana', 'postcode' => '10600', 'country' => 'Cuba'], "CP 10600 Ciudad Habana\nCuba"],
    // CZ — UPU CZE: `102 00 Praha` (two spaces).
    ['CZ', ['city' => 'Praha', 'postcode' => '102 00', 'country' => 'Czech Republic'], "102 00  Praha\nCzech Republic"],
    // DJ — UPU DJI: rural `Randa / 77601 TADJOURAH`, urban `77101 DJIBOUTI VILLE`.
    ['DJ', ['city' => 'Randa', 'state' => 'Tadjourah', 'postcode' => '77601', 'country' => 'Djibouti'], "Randa\n77601 Tadjourah\nDjibouti"],
    ['DJ', ['city' => 'Djibouti Ville', 'postcode' => '77101', 'country' => 'Djibouti'], "77101 Djibouti Ville\nDjibouti"],
    ['DJ', ['city' => 'Arta', 'state' => 'Arta', 'postcode' => '77201', 'country' => 'Djibouti'], "77201 Arta\nDjibouti"],
    // EE — Omniva + UPU EST: locality or county alternate, never both.
    ['EE', ['city' => 'Tallinn', 'postcode' => '11212', 'country' => 'Estonia'], "11212 Tallinn\nEstonia"],
    ['EE', ['state' => 'Viljandimaa', 'postcode' => '69501', 'country' => 'Estonia'], "69501 Viljandimaa\nEstonia"],
    // FM — USPS Pub 28 s224: state detail above, `CITY FM ZIP` last line.
    ['FM', ['city' => 'Palikir', 'state' => 'Pohnpei', 'postcode' => '96941', 'country' => 'Micronesia'], "Pohnpei\nPalikir FM 96941\nMicronesia"],
    ['FM', ['city' => 'Palikir', 'postcode' => '96941', 'country' => 'Micronesia'], "Palikir FM 96941\nMicronesia"],
    // GT — UPU GTM: `01065 Villa Canales, Guatemala` (dash only sometimes: space).
    ['GT', ['city' => 'Villa Canales', 'state' => 'Guatemala', 'postcode' => '01065', 'country' => 'Guatemala'], "01065 Villa Canales, Guatemala\nGuatemala"],
    // IR — UPU IRN: locality, street, province, postcode.
    ['IR', ['line1' => 'West 196 street', 'city' => 'Tehran', 'state' => 'Tehran Province', 'postcode' => '1619614153', 'country' => 'Iran'], "Tehran\nWest 196 street\nTehran Province\n1619614153\nIran"],
    // KI — UPU KIR: village above, `island postcode`.
    ['KI', ['city' => 'Eita Village', 'state' => 'Tarawa', 'postcode' => 'KI0107', 'country' => 'Kiribati'], "Eita Village\nTarawa KI0107\nKiribati"],
    // NI — UPU NIC: `12005 / Managua, Managua`.
    ['NI', ['city' => 'Managua', 'state' => 'Managua', 'postcode' => '12005', 'country' => 'Nicaragua'], "12005\nManagua, Managua\nNicaragua"],
    // NZ — UPU NZL: `WELLINGTON 6011` (city first).
    ['NZ', ['line1' => '100 Queen Street', 'city' => 'Auckland', 'postcode' => '1010', 'country' => 'New Zealand'], "100 Queen Street\nAuckland 1010\nNew Zealand"],
    // PF — UPU PYF: `98709 MAHINA TAHITI`.
    ['PF', ['city' => 'Mahina', 'state' => 'Tahiti', 'postcode' => '98709', 'country' => 'French Polynesia'], "98709 Mahina Tahiti\nFrench Polynesia"],
    // PG — UPU PNG: `BOROKO 111 NCD`.
    ['PG', ['city' => 'Boroko', 'state' => 'NCD', 'postcode' => '111', 'country' => 'Papua New Guinea'], "Boroko 111 NCD\nPapua New Guinea"],
    // SA — SPL National Address: city line above postcode.
    ['SA', ['city' => 'Riyadh', 'postcode' => '13337', 'country' => 'Saudi Arabia'], "Riyadh\n13337\nSaudi Arabia"],
    // TW — Chunghwa Post footer: `Da-an District, Taipei City 106409`.
    ['TW', ['city' => 'Da-an District', 'state' => 'Taipei City', 'postcode' => '106409', 'country' => 'Taiwan'], "Da-an District, Taipei City 106409\nTaiwan"],
    ['TW', ['city' => 'X', 'state' => 'X', 'postcode' => '106409', 'country' => 'Taiwan'], "X 106409\nTaiwan"],
    // UM — USPS Pub 28: single last line.
    ['UM', ['city' => 'Wake Island', 'postcode' => '96898', 'country' => 'United States Minor Outlying Islands'], "Wake Island 96898\nUnited States Minor Outlying Islands"],
    // VE — IPOSTEL tool: `Caracas 1010, Distrito Capital`.
    ['VE', ['city' => 'Caracas', 'state' => 'Distrito Capital', 'postcode' => '1010', 'country' => 'Venezuela'], "Caracas 1010, Distrito Capital\nVenezuela"],
    // US territories — island/division detail above, `CITY CODE ZIP` last line.
    ['VI', ['city' => 'Christiansted', 'state' => 'Saint Croix', 'postcode' => '00820', 'country' => 'Virgin Islands (US)'], "Saint Croix\nChristiansted VI 00820\nVirgin Islands (US)"],
    ['VI', ['city' => 'Christiansted', 'postcode' => '00820', 'country' => 'Virgin Islands (US)'], "Christiansted VI 00820\nVirgin Islands (US)"],
    ['GU', ['city' => 'Barrigada', 'postcode' => '96913', 'country' => 'Guam'], "Barrigada GU 96913\nGuam"],
    ['AS', ['city' => 'Pago Pago', 'postcode' => '96799', 'country' => 'American Samoa'], "Pago Pago AS 96799\nAmerican Samoa"],
    ['MH', ['city' => 'Majuro', 'postcode' => '96960', 'country' => 'Marshall Islands'], "Majuro MH 96960\nMarshall Islands"],
    ['PR', ['city' => 'San Juan', 'postcode' => '00901', 'country' => 'Puerto Rico'], "San Juan PR 00901\nPuerto Rico"],
    ['PW', ['city' => 'Koror', 'postcode' => '96940', 'country' => 'Palau'], "Koror PW 96940\nPalau"],
]);

it('skips case-insensitive duplicates and literals', function (): void {
    $albania = AddressFormatRenderer::format('AL', AddressData::from([
        'city' => 'Tirana',
        'state' => 'TIRANA',
        'postcode' => '1001',
        'countryCode' => 'AL',
    ]));

    $singapore = AddressFormatRenderer::format('SG', AddressData::from([
        'line1' => '1 Raffles Place',
        'city' => 'Singapore',
        'state' => 'Singapore',
        'postcode' => '048616',
        'countryCode' => 'SG',
    ]));

    expect($albania)->toBe("1001\nTirana\nAlbania")
        ->and($singapore)->toBe("1 Raffles Place\nSingapore 048616");
});

it('abbreviates states and restructures conditional lines', function (): void {
    $full = AddressFormatRenderer::format('MX', AddressData::from([
        'city' => 'Guadalajara',
        'state' => 'Jalisco',
        'postcode' => '44100',
        'countryCode' => 'MX',
    ]));

    $noPostcode = AddressFormatRenderer::format('MX', AddressData::from([
        'city' => 'Guadalajara',
        'state' => 'Jalisco',
        'countryCode' => 'MX',
    ]));

    expect($full)->toBe("44100 Guadalajara, JAL\nMexico")
        ->and($noPostcode)->toBe("Guadalajara\nJalisco\nMexico");
});

it('fails loudly on unknown definitions and ops', function (): void {
    expect(fn (): string => AddressFormatRenderer::format('XX', AddressData::from([])))
        ->toThrow(InvalidArgumentException::class, 'No address format definition');
});

function withFormatDefinition(array $lines, Closure $assert, array $extra = []): void
{
    $property = new ReflectionClass(AddressFormatRenderer::class)->getProperty('definitions');
    $property->setAccessible(true);
    $previous = $property->getValue();

    $property->setValue(['ZZ' => array_merge(['display' => 'Zed', 'lines' => $lines], $extra)]);

    try {
        $assert();
    } finally {
        $property->setValue($previous);
    }
}

it('fails loudly on unknown fields, dangling ops, and malformed lines', function (): void {
    $address = AddressData::from(['city' => 'Springfield', 'state' => 'Illinois']);

    withFormatDefinition([['join' => ['postcod']]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'Unknown address format field [postcod]');
    });

    withFormatDefinition([['join' => ['city:bogus']]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'Unknown address format op [bogus]');
    });

    withFormatDefinition([['join' => ['state:in']]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs a comma-separated value list');
    });

    withFormatDefinition([['join' => ['city:!dup']]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs a field reference');
    });

    withFormatDefinition([['if' => 'city']], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs each, join, or country');
    });

    withFormatDefinition([['each' => 'city']], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs each as a field list');
    });

    withFormatDefinition([['join' => [['bogus' => true]]]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs lit, alt, or join');
    });

    withFormatDefinition([['join' => [['alt' => 'city']]]], function () use ($address): void {
        expect(fn (): string => AddressFormatRenderer::format('ZZ', $address))
            ->toThrow(InvalidArgumentException::class, 'needs alt as an option list');
    });
});

it('skips empty literals instead of injecting blank segments', function (): void {
    withFormatDefinition([['join' => ['city', ['lit' => '']]]], function (): void {
        $output = AddressFormatRenderer::format('ZZ', AddressData::from(['city' => 'Springfield']));

        expect($output)->toBe('Springfield');
    });
});

it('summarizes the MY ruled format: locality, partner, state', function (): void {
    // Explicit summary spec: rural keeps the mukim ahead of the daerah
    // partner; urban collapses to town + state (writer leaves locality
    // null when the partner is a town).
    $rural = AddressFormatRenderer::summaryParts('MY', AddressData::from([
        'line1' => 'Pt Hj Ibrahim, Bagan Mukim 2',
        'components' => ['locality' => 'Bagan', 'postcode_partner' => 'Batu Pahat'],
        'state' => 'Johor',
        'postcode' => '83000',
        'countryCode' => 'MY',
    ]));

    $urban = AddressFormatRenderer::summaryParts('MY', AddressData::from([
        'line1' => '2, Jalan Gunung Nuang U11/9D',
        'line2' => 'Taman Bukit Bandaraya, Seksyen U11',
        'components' => ['postcode_partner' => 'Shah Alam'],
        'state' => 'Selangor',
        'postcode' => '40170',
        'countryCode' => 'MY',
    ]));

    expect($rural)->toBe(['Bagan', 'Batu Pahat', 'Johor'])
        ->and($urban)->toBe(['Shah Alam', 'Selangor']);
});

it('falls back to non-street, non-postcode, non-country pieces in line order', function (): void {
    $us = AddressFormatRenderer::summaryParts('US', AddressData::from([
        'line1' => '100 Main St',
        'city' => 'Springfield',
        'state' => 'Illinois',
        'postcode' => '62701',
        'countryCode' => 'US',
    ]));

    $mxFull = AddressFormatRenderer::summaryParts('MX', AddressData::from([
        'city' => 'Guadalajara',
        'state' => 'Jalisco',
        'postcode' => '44100',
        'countryCode' => 'MX',
    ]));

    $mxNoPostcode = AddressFormatRenderer::summaryParts('MX', AddressData::from([
        'city' => 'Guadalajara',
        'state' => 'Jalisco',
        'countryCode' => 'MX',
    ]));

    $singapore = AddressFormatRenderer::summaryParts('SG', AddressData::from([
        'line1' => '1 Raffles Place',
        'city' => 'Singapore',
        'state' => 'Singapore',
        'postcode' => '048616',
        'countryCode' => 'SG',
    ]));

    expect($us)->toBe(['Springfield', 'IL'])
        ->and($mxFull)->toBe(['Guadalajara', 'JAL'])
        ->and($mxNoPostcode)->toBe(['Guadalajara', 'Jalisco'])
        ->and($singapore)->toBe([]);
});

it('keeps nested postcodes and literals out of fallback summaries', function (): void {
    withFormatDefinition([
        ['join' => ['line1', ['join' => ['city', 'postcode'], 'sep' => ' '], ['lit' => '-'], ['alt' => ['postcode', 'state']]]],
        ['country' => true],
    ], function (): void {
        $parts = AddressFormatRenderer::summaryParts('ZZ', AddressData::from([
            'line1' => '100 Main St',
            'city' => 'Springfield',
            'state' => 'Illinois',
            'postcode' => '62701',
            'countryCode' => 'ZZ',
        ]));

        expect($parts)->toBe(['Springfield', 'Illinois']);
    });
});

it('honors line gates in fallback summaries', function (): void {
    withFormatDefinition([
        ['join' => ['city'], 'if' => 'postcode'],
        ['join' => ['state']],
    ], function (): void {
        $with = AddressFormatRenderer::summaryParts('ZZ', AddressData::from([
            'city' => 'Springfield', 'state' => 'Illinois', 'postcode' => '62701',
        ]));
        $without = AddressFormatRenderer::summaryParts('ZZ', AddressData::from([
            'city' => 'Springfield', 'state' => 'Illinois',
        ]));

        expect($with)->toBe(['Springfield', 'Illinois'])->and($without)->toBe(['Illinois']);
    });
});

it('fails loudly on unknown summary definitions and malformed summary specs', function (): void {
    expect(fn (): array => AddressFormatRenderer::summaryParts('XX', AddressData::from([])))
        ->toThrow(InvalidArgumentException::class, 'No address format definition');

    withFormatDefinition([], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from([])))
            ->toThrow(InvalidArgumentException::class, 'needs a join field list');
    }, ['summary' => ['join' => 'city']]);

    withFormatDefinition([['join' => ['city']]], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from(['city' => 'Springfield'])))
            ->toThrow(InvalidArgumentException::class, 'needs a join field list');
    }, ['summary' => 'city']);

    withFormatDefinition([['if' => 'city']], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from(['city' => 'Springfield'])))
            ->toThrow(InvalidArgumentException::class, 'needs each, join, or country');
    });
});

it('rejects street, postcode, and country leaves in explicit summaries', function (): void {
    withFormatDefinition([['join' => ['city']]], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from(['city' => 'Springfield'])))
            ->toThrow(InvalidArgumentException::class, 'must not reference [postcode]');
    }, ['summary' => ['join' => ['city', 'postcode']]]);

    withFormatDefinition([['join' => ['city']]], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from(['city' => 'Springfield'])))
            ->toThrow(InvalidArgumentException::class, 'must not reference [line1]');
    }, ['summary' => ['join' => [['join' => ['city', 'line1']]]]]);

    withFormatDefinition([['join' => ['city']]], function (): void {
        expect(fn (): array => AddressFormatRenderer::summaryParts('ZZ', AddressData::from(['city' => 'Springfield'])))
            ->toThrow(InvalidArgumentException::class, 'needs a join field list');
    }, ['summary' => null]);
});
