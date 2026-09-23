<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Australia\AustraliaAddressFormatter;
use AIArmada\Addressing\Geography\Australia\AustraliaGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;

it('formats Australian addresses with double-spaced parts', function (): void {
    $formatted = app(AustraliaAddressFormatter::class)->format(AddressData::from([
        'line1' => '113 BOND ST',
        'city' => 'MELBOURNE',
        'state' => 'Victoria',
        'postcode' => '3000',
        'country_code' => 'AU',
    ]));

    expect($formatted)->toBe("113 BOND ST\nMELBOURNE  VIC  3000\nAustralia");
});

it('ships 537 local government areas under states with parent links', function (): void {
    $areas = app(AustraliaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(537)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($l2->where('type', 'city'))->toHaveCount(128)
        ->and($l2->where('type', 'shire'))->toHaveCount(240)
        ->and($l2->where('type', 'council'))->toHaveCount(91)
        ->and($l2->where('type', 'region'))->toHaveCount(51)
        ->and($l2->where('type', 'town'))->toHaveCount(13)
        ->and($l2->where('type', 'rural_city'))->toHaveCount(7)
        ->and($l2->where('type', 'municipality'))->toHaveCount(6)
        ->and($l2->where('type', 'borough'))->toHaveCount(1)
        ->and($byId->get('au:city:city-of-sydney')->parentSourceId)->toBe('au:state:new-south-wales')
        ->and($byId->get('au:shire:yarra-ranges-shire')->parentSourceId)->toBe('au:state:victoria')
        ->and($byId->get('au:borough:borough-of-queenscliffe')->parentSourceId)->toBe('au:state:victoria')
        ->and($byId->get('au:city:south-australia:city-of-campbelltown')->parentSourceId)->toBe('au:state:south-australia')
        ->and($byId->get('au:council:tasmania:central-coast-council')->parentSourceId)->toBe('au:state:tasmania');
});

it('declares postal abbreviations for all 8 states and territories', function (): void {
    $names = app(AustraliaGeographyProvider::class)->areaNames(new AddressCountry);

    expect($names)->toHaveCount(8)
        ->and($names['au:state:new-south-wales'][0])->toBe(['name' => 'NSW', 'name_type' => 'abbreviation'])
        ->and($names['au:state:queensland'][0]['name'])->toBe('QLD')
        ->and($names['au:territory:northern-territory'][0]['name'])->toBe('NT');
});
