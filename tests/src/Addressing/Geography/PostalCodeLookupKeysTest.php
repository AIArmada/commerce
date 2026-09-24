<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryPostalCodeNormalizer;
use AIArmada\Addressing\Geography\Aland\AlandGeographyProvider;
use AIArmada\Addressing\Geography\AmericanSamoa\AmericanSamoaGeographyProvider;
use AIArmada\Addressing\Geography\Andorra\AndorraGeographyProvider;
use AIArmada\Addressing\Geography\Anguilla\AnguillaGeographyProvider;
use AIArmada\Addressing\Geography\Argentina\ArgentinaGeographyProvider;
use AIArmada\Addressing\Geography\Azerbaijan\AzerbaijanGeographyProvider;
use AIArmada\Addressing\Geography\Barbados\BarbadosGeographyProvider;
use AIArmada\Addressing\Geography\Bermuda\BermudaGeographyProvider;
use AIArmada\Addressing\Geography\Canada\CanadaGeographyProvider;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Greece\GreeceGeographyProvider;
use AIArmada\Addressing\Geography\Guam\GuamGeographyProvider;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;
use AIArmada\Addressing\Geography\Kiribati\KiribatiGeographyProvider;
use AIArmada\Addressing\Geography\Latvia\LatviaGeographyProvider;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Geography\Liberia\LiberiaGeographyProvider;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Geography\Malta\MaltaGeographyProvider;
use AIArmada\Addressing\Geography\MarshallIslands\MarshallIslandsGeographyProvider;
use AIArmada\Addressing\Geography\Micronesia\MicronesiaGeographyProvider;
use AIArmada\Addressing\Geography\Moldova\MoldovaGeographyProvider;
use AIArmada\Addressing\Geography\Montserrat\MontserratGeographyProvider;
use AIArmada\Addressing\Geography\Netherlands\NetherlandsGeographyProvider;
use AIArmada\Addressing\Geography\Palau\PalauGeographyProvider;
use AIArmada\Addressing\Geography\Poland\PolandGeographyProvider;
use AIArmada\Addressing\Geography\PuertoRico\PuertoRicoGeographyProvider;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;
use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisGeographyProvider;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesGeographyProvider;
use AIArmada\Addressing\Geography\Slovakia\SlovakiaGeographyProvider;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Geography\Taiwan\TaiwanGeographyProvider;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;
use AIArmada\Addressing\Geography\UnitedStates\UnitedStatesGeographyProvider;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;

it('expands country-specific postcode lookup keys', function (string $providerClass, string $input, array $expected): void {
    $provider = new $providerClass;

    expect($provider)->toBeInstanceOf(CountryPostalCodeNormalizer::class)
        ->and($provider->postalCodeLookupKeys($input))->toBe($expected);
})->with([
    'AR full CABA CPA' => [ArgentinaGeographyProvider::class, 'C1406DOB', ['C1406DOB', 'C1406']],
    'AR full interior CPA' => [ArgentinaGeographyProvider::class, 'N4419ABC', ['N4419ABC', '4419']],
    'AR base CABA passes through' => [ArgentinaGeographyProvider::class, 'C1406', ['C1406']],
    'AR base interior passes through' => [ArgentinaGeographyProvider::class, '4419', ['4419']],
    'AR lowercase full CPA' => [ArgentinaGeographyProvider::class, 'c1406dob', ['C1406DOB', 'C1406']],
    'NL full postcode' => [NetherlandsGeographyProvider::class, '1011AB', ['1011AB', '1011']],
    'NL full postcode spaced' => [NetherlandsGeographyProvider::class, '1011 AB', ['1011 AB', '1011']],
    'NL prefix passes through' => [NetherlandsGeographyProvider::class, '1011', ['1011']],
    'IE full eircode' => [IrelandGeographyProvider::class, 'A41F4E2', ['A41F4E2', 'A41']],
    'IE full eircode spaced' => [IrelandGeographyProvider::class, 'A41 F4E2', ['A41F4E2', 'A41']],
    'IE routing key passes through' => [IrelandGeographyProvider::class, 'A41', ['A41']],
    'US ZIP+4 dashed' => [UnitedStatesGeographyProvider::class, '12345-6789', ['12345-6789', '12345']],
    'US ZIP+4 plain' => [UnitedStatesGeographyProvider::class, '123456789', ['123456789', '12345']],
    'US ZIP passes through' => [UnitedStatesGeographyProvider::class, '12345', ['12345']],
    'PR ZIP+4' => [PuertoRicoGeographyProvider::class, '00601-1234', ['00601-1234', '00601']],
    'GU ZIP+4' => [GuamGeographyProvider::class, '96910-1234', ['96910-1234', '96910']],
    'VI ZIP+4' => [USVirginIslandsGeographyProvider::class, '00801-1234', ['00801-1234', '00801']],
    'AS ZIP+4' => [AmericanSamoaGeographyProvider::class, '96799-1234', ['96799-1234', '96799']],
    'MH ZIP+4' => [MarshallIslandsGeographyProvider::class, '96960-1234', ['96960-1234', '96960']],
    'FM ZIP+4' => [MicronesiaGeographyProvider::class, '96941-1234', ['96941-1234', '96941']],
    'PW ZIP+4' => [PalauGeographyProvider::class, '96939-1234', ['96939-1234', '96939']],
    'TW 6-digit' => [TaiwanGeographyProvider::class, '100001', ['100001', '100']],
    'TW 3-digit passes through' => [TaiwanGeographyProvider::class, '100', ['100']],
    'CZ spaceless' => [CzechRepublicGeographyProvider::class, '10000', ['10000', '100 00']],
    'CZ spaced passes through' => [CzechRepublicGeographyProvider::class, '100 00', ['100 00']],
    'SK spaceless' => [SlovakiaGeographyProvider::class, '01001', ['01001', '010 01']],
    'SE spaceless' => [SwedenGeographyProvider::class, '11451', ['11451', '114 51']],
    'SE spaced passes through' => [SwedenGeographyProvider::class, '114 51', ['114 51']],
    'PL dashless' => [PolandGeographyProvider::class, '00002', ['00002', '00-002']],
    'PL dashed passes through' => [PolandGeographyProvider::class, '00-002', ['00-002']],
    'GR spaced strips' => [GreeceGeographyProvider::class, '104 31', ['104 31', '10431']],
    'GR spaceless passes through' => [GreeceGeographyProvider::class, '10431', ['10431']],
    'AX bare gains prefix' => [AlandGeographyProvider::class, '22100', ['22100', 'AX-22100']],
    'AX spaceless gains dash' => [AlandGeographyProvider::class, 'AX22100', ['AX22100', 'AX-22100']],
    'AX full passes through' => [AlandGeographyProvider::class, 'AX-22100', ['AX-22100']],
    'FO bare gains prefix' => [FaroeIslandsGeographyProvider::class, '100', ['100', 'FO-100']],
    'MD bare gains prefix' => [MoldovaGeographyProvider::class, '2000', ['2000', 'MD-2000']],
    'LV bare gains prefix' => [LatviaGeographyProvider::class, '1001', ['1001', 'LV-1001']],
    'LU bare gains prefix' => [LuxembourgGeographyProvider::class, '1111', ['1111', 'L-1111']],
    'AI bare gains prefix' => [AnguillaGeographyProvider::class, '2640', ['2640', 'AI-2640']],
    'AD bare gains prefix' => [AndorraGeographyProvider::class, '100', ['100', 'AD100']],
    'AD one-letter-short prefix canonicalizes' => [AndorraGeographyProvider::class, 'A501', ['A501', 'AD501']],
    'BB bare gains prefix' => [BarbadosGeographyProvider::class, '11000', ['11000', 'BB11000']],
    'BB one-letter-short prefix canonicalizes' => [BarbadosGeographyProvider::class, 'B26028', ['B26028', 'BB26028']],
    'HT bare gains prefix' => [HaitiGeographyProvider::class, '1110', ['1110', 'HT1110']],
    'HT one-letter-short prefix canonicalizes' => [HaitiGeographyProvider::class, 'H6110', ['H6110', 'HT6110']],
    'KI bare gains prefix' => [KiribatiGeographyProvider::class, '0101', ['0101', 'KI0101']],
    'KI one-letter-short prefix canonicalizes' => [KiribatiGeographyProvider::class, 'K0107', ['K0107', 'KI0107']],
    'KN bare gains prefix' => [SaintKittsAndNevisGeographyProvider::class, '0101', ['0101', 'KN0101']],
    'KN one-letter-short prefix canonicalizes' => [SaintKittsAndNevisGeographyProvider::class, 'K0602', ['K0602', 'KN0602']],
    'MS bare gains prefix' => [MontserratGeographyProvider::class, '1110', ['1110', 'MSR1110']],
    'MS one-letter-short prefix canonicalizes' => [MontserratGeographyProvider::class, 'MS1110', ['MS1110', 'MSR1110']],
    'VC bare gains prefix' => [SaintVincentAndTheGrenadinesGeographyProvider::class, '0110', ['0110', 'VC0110']],
    'VC one-letter-short prefix canonicalizes' => [SaintVincentAndTheGrenadinesGeographyProvider::class, 'V0120', ['V0120', 'VC0120']],
    'AZ bare gains prefix' => [AzerbaijanGeographyProvider::class, '0100', ['0100', 'AZ 0100']],
    'AZ spaceless gains space' => [AzerbaijanGeographyProvider::class, 'AZ0100', ['AZ0100', 'AZ 0100']],
    'BM compact gains space' => [BermudaGeographyProvider::class, 'CR01', ['CR01', 'CR 01']],
    'MT compact gains space' => [MaltaGeographyProvider::class, 'ATD1000', ['ATD1000', 'ATD 1000']],
    'LC compact gains space' => [SaintLuciaGeographyProvider::class, 'LC01101', ['LC01101', 'LC01 101']],
    'SH compact gains space' => [SaintHelenaGeographyProvider::class, 'STHL1ZZ', ['STHL1ZZ', 'STHL 1ZZ']],
    'TC compact gains space' => [TurksAndCaicosGeographyProvider::class, 'TKCA1ZZ', ['TKCA1ZZ', 'TKCA 1ZZ']],
    'CA full postcode spaced' => [CanadaGeographyProvider::class, 'H3Z 2Y7', ['H3Z2Y7', 'H3Z']],
    'CA full postcode compact' => [CanadaGeographyProvider::class, 'H3Z2Y7', ['H3Z2Y7', 'H3Z']],
    'CA lowercase full postcode' => [CanadaGeographyProvider::class, 'h3z 2y7', ['H3Z2Y7', 'H3Z']],
    'CA FSA passes through' => [CanadaGeographyProvider::class, 'H3Z', ['H3Z']],
    'CA rural FSA passes through' => [CanadaGeographyProvider::class, 'T0A', ['T0A']],
    'CA partial FSA passes through' => [CanadaGeographyProvider::class, 'K1', ['K1']],
    'LB sector suffix spaced' => [LebanonGeographyProvider::class, '1107 2020', ['1107 2020', '1107']],
    'LB sector suffix compact' => [LebanonGeographyProvider::class, '11072020', ['11072020', '1107']],
    'LB base passes through' => [LebanonGeographyProvider::class, '1107', ['1107']],
    'LR delivery unit' => [LiberiaGeographyProvider::class, '1000-10', ['1000-10', '1000']],
    'LR base passes through' => [LiberiaGeographyProvider::class, '1000', ['1000']],
    'unknown input passes through' => [ArgentinaGeographyProvider::class, 'XYZ', ['XYZ']],
]);

it('keeps every bundled code resolvable through its own normalizer', function (): void {
    $cases = [
        [ArgentinaGeographyProvider::class, 'argentina'],
        [NetherlandsGeographyProvider::class, 'netherlands'],
        [IrelandGeographyProvider::class, 'ireland'],
        [UnitedStatesGeographyProvider::class, 'united-states'],
        [PuertoRicoGeographyProvider::class, 'puerto-rico'],
        [GuamGeographyProvider::class, 'guam'],
        [USVirginIslandsGeographyProvider::class, 'us-virgin-islands'],
        [AmericanSamoaGeographyProvider::class, 'american-samoa'],
        [MarshallIslandsGeographyProvider::class, 'marshall-islands'],
        [MicronesiaGeographyProvider::class, 'micronesia'],
        [PalauGeographyProvider::class, 'palau'],
        [TaiwanGeographyProvider::class, 'taiwan'],
        [CzechRepublicGeographyProvider::class, 'czech-republic'],
        [SlovakiaGeographyProvider::class, 'slovakia'],
        [SwedenGeographyProvider::class, 'sweden'],
        [PolandGeographyProvider::class, 'poland'],
        [GreeceGeographyProvider::class, 'greece'],
        [AlandGeographyProvider::class, 'aland'],
        [FaroeIslandsGeographyProvider::class, 'faroe-islands'],
        [MoldovaGeographyProvider::class, 'moldova'],
        [LatviaGeographyProvider::class, 'latvia'],
        [LuxembourgGeographyProvider::class, 'luxembourg'],
        [AnguillaGeographyProvider::class, 'anguilla'],
        [AndorraGeographyProvider::class, 'andorra'],
        [BarbadosGeographyProvider::class, 'barbados'],
        [HaitiGeographyProvider::class, 'haiti'],
        [KiribatiGeographyProvider::class, 'kiribati'],
        [SaintKittsAndNevisGeographyProvider::class, 'saint-kitts-and-nevis'],
        [MontserratGeographyProvider::class, 'montserrat'],
        [SaintVincentAndTheGrenadinesGeographyProvider::class, 'saint-vincent-and-the-grenadines'],
        [AzerbaijanGeographyProvider::class, 'azerbaijan'],
        [BermudaGeographyProvider::class, 'bermuda'],
        [CanadaGeographyProvider::class, 'canada'],
        [MaltaGeographyProvider::class, 'malta'],
        [SaintLuciaGeographyProvider::class, 'saint-lucia'],
        [SaintHelenaGeographyProvider::class, 'saint-helena'],
        [TurksAndCaicosGeographyProvider::class, 'turks-and-caicos'],
        [LebanonGeographyProvider::class, 'lebanon'],
        [LiberiaGeographyProvider::class, 'liberia'],
    ];

    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $checked = 0;
    $failures = [];

    foreach ($cases as [$providerClass, $slug]) {
        $provider = new $providerClass;
        $path = $dir . "/{$slug}-postal-codes.csv";
        $handle = fopen($path, 'r');
        expect($handle)->not->toBeFalse();
        fgetcsv($handle, escape: '\\');
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $code = mb_trim((string) ($row[1] ?? ''));
            if ($code === '') {
                continue;
            }
            if (! in_array($code, $provider->postalCodeLookupKeys($code), true)) {
                $failures[] = "{$slug}:{$code}";
            }
            $checked++;
        }
        fclose($handle);
    }

    expect($failures)->toBe([])
        ->and($checked)->toBeGreaterThan(90000);
});
