<?php

declare(strict_types=1);

// State mappings join provider data by string keys with no compile-time link:
// mapping keys must match state definition codes, and abbreviation maps must
// cover state names. This test locks those joins for every provider.

use AIArmada\Addressing\Contracts\CountryGeographyProvider;

function addressingProviderStateDefinitions(object $provider): array
{
    $method = new ReflectionMethod($provider, 'stateDefinitions');
    $method->setAccessible(true);

    return $method->invoke($provider);
}

it('maps every state mapping key to a state definition', function (): void {
    $dangling = [];

    foreach (config('addressing.geography.providers') as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider) {
            continue;
        }

        $defCodes = array_map(
            fn ($d) => (string) ($d['code'] ?? ''),
            addressingProviderStateDefinitions($provider)
        );
        $mapKeys = array_map('strval', array_keys($provider->stateAreaMappings()));
        $missing = array_diff($mapKeys, $defCodes);

        if ($missing !== []) {
            $dangling[$provider->countryCode()] = array_values($missing);
        }
    }

    expect($dangling)->toBe([]);
});

it('documents the only states intentionally left unmapped', function (): void {
    // Mappings link states to areas at one tier; these countries define an
    // additional tier that has no area links by design. Exact sets: any drift
    // here is a data change that deserves review, not a silent pass.
    $expected = [
        // 64 districts; mappings cover the 8 divisions.
        'BD' => '01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64',
        // 50 provinces; mappings cover the 19 autonomous communities.
        'ES' => 'A,AB,AL,AV,B,BA,BI,BU,C,CA,CC,CO,CR,CS,CU,GC,GI,GR,GU,H,HU,J,L,LE,LO,LU,M,MA,MU,NA,O,OR,P,PM,PO,S,SA,SE,SG,SO,SS,T,TE,TF,TO,V,VA,VI,Z,ZA',
        // 75 provinces/prefectures; mappings cover the 12 regions.
        'MA' => 'AGD,AOU,ASZ,AZI,BEM,BER,BES,BOD,BOM,BRR,CAS,CHE,CHI,CHT,DRI,ERR,ESI,ESM,FAH,FES,FIG,FQH,GUE,GUF,HAJ,HAO,HOC,IFR,INE,JDI,JRA,KEN,KES,KHE,KHN,KHO,LAA,LAR,MAR,MDF,MED,MEK,MID,MOH,MOU,NAD,NOU,OUA,OUD,OUJ,OUZ,RAB,REH,SAF,SAL,SEF,SET,SIB,SIF,SIK,SIL,SKH,TAF,TAI,TAO,TAR,TAT,TAZ,TET,TIN,TIZ,TNG,TNT,YUS,ZAG',
        // 24 municipalities; mappings cover the 2 island chains.
        'MH' => 'ALK,ALL,ARN,AUR,EBO,ENI,JAB,JAL,KIL,KWA,LAE,LIB,LIK,MAJ,MAL,MEJ,MIL,NMK,NMU,RON,UJA,UTI,WTH,WTJ',
        // 17 regions (only NCR '00' mapped) + 82 provinces; 16 regions left unmapped.
        'PH' => '01,02,03,05,06,07,08,09,10,11,12,13,14,15,40,41',
    ];

    $unmapped = [];

    foreach (config('addressing.geography.providers') as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider) {
            continue;
        }

        $defCodes = array_map(
            fn ($d) => (string) ($d['code'] ?? ''),
            addressingProviderStateDefinitions($provider)
        );
        $mapKeys = array_map('strval', array_keys($provider->stateAreaMappings()));
        $missing = array_diff($defCodes, $mapKeys);

        if ($missing !== []) {
            $sorted = array_values($missing);
            sort($sorted);
            $unmapped[$provider->countryCode()] = implode(',', $sorted);
        }
    }

    ksort($unmapped);

    expect($unmapped)->toBe($expected);
});

it('covers every state name in abbreviation maps', function (): void {
    $defNamesByCountry = [];

    foreach (config('addressing.geography.providers') as $providerClass) {
        $provider = app($providerClass);

        if (! $provider instanceof CountryGeographyProvider) {
            continue;
        }

        $defNamesByCountry[$provider->countryCode()] = array_map(
            fn ($d) => (string) ($d['name'] ?? ''),
            addressingProviderStateDefinitions($provider)
        );
    }

    $gaps = [];

    foreach (config('addressing.formatters') as $formatterClass) {
        $constants = (new ReflectionClass($formatterClass))->getConstants();

        foreach ($constants as $name => $value) {
            if (! str_contains($name, 'ABBREVIATION') || ! is_array($value)) {
                continue;
            }

            $missing = array_diff($defNamesByCountry[$formatterClass::countryCode()] ?? [], array_keys($value));

            if ($missing !== []) {
                $gaps[$formatterClass::countryCode()] = array_values($missing);
            }
        }
    }

    expect($gaps)->toBe([]);
});
