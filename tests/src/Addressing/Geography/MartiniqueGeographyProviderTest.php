<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Martinique\MartiniqueAddressFormatter;
use AIArmada\Addressing\Geography\Martinique\MartiniqueGeographyProvider;

it('formats Martinican addresses with the postcode left of the locality', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => '25 RUE CARNOT',
        'city' => 'LA TRINITE',
        'postcode' => '97220',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("25 RUE CARNOT\n97220 LA TRINITE\nMartinique");
});
it('formats Martinican Fort-de-France addresses with the town postcode', function (): void {
    $formatted = app(MartiniqueAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la Liberté 9',
        'city' => 'FORT-DE-FRANCE',
        'postcode' => '97200',
        'country_code' => 'MQ',
    ]));

    expect($formatted)->toBe("Rue de la Liberté 9\n97200 FORT-DE-FRANCE\nMartinique");
});

it('ships 34 communes under districts with parent links', function (): void {
    $areas = app(MartiniqueGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'commune');

    expect($l2)->toHaveCount(34)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('mq:commune:schoelcher')->name)->toBe('Schœlcher')
        ->and($byId->get('mq:commune:fort-de-france')->name)->toBe('Fort-de-France')
        ->and($byId->get('mq:commune:sainte-anne')->name)->toBe('Sainte-Anne');
});

it('labels tiers Arrondissement and Commune', function (): void {
    $provider = app(MartiniqueGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['district' => 'Arrondissement', 'commune' => 'Commune'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
