<?php

declare(strict_types=1);

use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Laos\LaosAddressFormatter;
use AIArmada\Addressing\Geography\Laos\LaosGeographyProvider;

it('formats Laotian addresses with the postcode left of the locality', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'XAYSETHA',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 XAYSETHA\nLaos");
});
it('formats Laotian addresses with the province below the postcode locality line', function (): void {
    $formatted = app(LaosAddressFormatter::class)->format(AddressData::from([
        'line1' => '14, rue That Louang',
        'city' => 'Xaysetha',
        'state' => 'Vientiane',
        'postcode' => '01160',
        'country_code' => 'LA',
    ]));

    expect($formatted)->toBe("14, rue That Louang\n01160 Xaysetha\nVientiane\nLaos");
});

it('ships 148 districts under provinces with parent links', function (): void {
    $areas = app(LaosGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('type', 'district');

    expect($l2)->toHaveCount(148)
        ->and($l2->pluck('parentSourceId')->every(fn ($p) => $byId->has($p)))->toBeTrue()
        ->and($byId->get('la:district:chanthabuly')->name)->toBe('Chanthabuly')
        ->and($byId->get('la:district:sikhottabong')->name)->toBe('Sikhottabong')
        ->and($byId->get('la:district:xaysetha')->name)->toBe('Xaysetha');
});

it('labels tiers Khoueng and Muang', function (): void {
    $provider = app(LaosGeographyProvider::class);

    expect($provider->areaTypeLabels())->toBe(['province' => 'Khoueng', 'district' => 'Muang'])
        ->and($provider->stateAreaTypeLabels())->toBe([]);
});
