<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\DemocraticRepublicOfCongo\DemocraticRepublicOfCongoGeographyProvider;

it('ships 26 provinces with ISO 3166-2:CD codes', function (): void {
    $areas = app(DemocraticRepublicOfCongoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('level', 1))->toHaveCount(26)
        ->and($byId->get('cd:province:kongo-central')->code)->toBe('BC')
        ->and($byId->get('cd:province:kinshasa')->code)->toBe('KN')
        ->and($byId->get('cd:province:nord-kivu')->code)->toBe('NK')
        ->and($byId->get('cd:province:sud-kivu')->code)->toBe('SK')
        ->and($byId->get('cd:province:tshuapa')->code)->toBe('TU');
});

it('ships 145 territories with COD-AB counts and Kinshasa terminal', function (): void {
    $areas = app(DemocraticRepublicOfCongoGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $l2 = $areas->where('level', 2);

    expect($l2)->toHaveCount(145)
        // Per-province COD-AB territory counts.
        ->and($l2->where('parentSourceId', 'cd:province:kongo-central'))->toHaveCount(10)
        ->and($l2->where('parentSourceId', 'cd:province:mongala'))->toHaveCount(3)
        ->and($l2->where('parentSourceId', 'cd:province:mai-ndombe'))->toHaveCount(8)
        ->and($l2->where('parentSourceId', 'cd:province:sud-kivu'))->toHaveCount(8)
        // Kinshasa terminal: its only COD-AB admin2 is the Kinshasa ville.
        ->and($l2->where('parentSourceId', 'cd:province:kinshasa'))->toHaveCount(0)
        // Deliberate spelling keeps: EN+FR wiki over COD-AB.
        ->and($byId->get('cd:territory:kanyama')->name)->toBe('Kanyama')
        ->and($byId->get('cd:territory:kanyama')->parentSourceId)->toBe('cd:province:haut-lomami')
        ->and($byId->get('cd:territory:gandajika')->name)->toBe('Gandajika')
        ->and($byId->get('cd:territory:gandajika')->parentSourceId)->toBe('cd:province:lomami');
});
