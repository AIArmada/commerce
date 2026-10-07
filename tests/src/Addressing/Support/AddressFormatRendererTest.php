<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Support\AddressFormatRenderer;

it('renders street components from the data map', function (): void {
    $output = AddressFormatRenderer::format('MY', AddressData::from([
        'line1' => '1 Jalan Ampang',
        'components' => ['mukim' => 'Ampang', 'subdistrict' => '', 'district' => 'Hulu Langat'],
        'city' => 'Ampang',
        'state' => 'Selangor',
        'postcode' => '68000',
        'countryCode' => 'MY',
    ]));

    expect($output)->toBe("1 Jalan Ampang\nAmpang\nHulu Langat\n68000 Ampang\nSelangor\nMalaysia");
});

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

function withFormatDefinition(array $lines, Closure $assert): void
{
    $property = new ReflectionClass(AddressFormatRenderer::class)->getProperty('definitions');
    $property->setAccessible(true);
    $previous = $property->getValue();

    $property->setValue(['ZZ' => ['display' => 'Zed', 'lines' => $lines]]);

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
