<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\Singapore\SingaporeGeographyProvider;
use AIArmada\Addressing\Models\State;

it('preserves globally seeded states and adds missing Singapore districts', function (): void {
    $country = $this->seedCountry('SG');
    $state = State::query()->create([
        'country_id' => $country->id,
        'code' => '01',
        'name' => 'Central',
        'label' => 'Central',
    ]);

    app(SingaporeGeographyProvider::class)->seed($country);

    $state->refresh();

    expect($state->name)->toBe('Central Singapore')
        ->and(State::query()->where('country_id', $country->id)->count())->toBeGreaterThan(1);
});

it('pins the verified postal tree of 28 districts and 81 sectors', function (): void {
    $areas = app(SingaporeGeographyProvider::class)->addressAreaSource()->areas()->collect();

    $sectors = $areas->where('type', 'postal_sector');

    expect($areas->where('type', 'postal_district'))->toHaveCount(28)
        ->and($sectors)->toHaveCount(81)
        ->and($sectors->pluck('code'))->not->toContain('74')
        ->and($sectors->pluck('code')->max())->toBe('82');

    $byId = $areas->keyBy->sourceId;

    // Non-obvious links: sector numbers do not match district numbers.
    expect($byId->get('sg:postal-sector:14')->parentSourceId)->toBe('sg:postal-district:03')
        ->and($byId->get('sg:postal-sector:09')->parentSourceId)->toBe('sg:postal-district:04')
        ->and($byId->get('sg:postal-sector:23')->parentSourceId)->toBe('sg:postal-district:09')
        ->and($byId->get('sg:postal-sector:81')->parentSourceId)->toBe('sg:postal-district:17')
        ->and($byId->get('sg:postal-sector:82')->parentSourceId)->toBe('sg:postal-district:19')
        ->and($byId->get('sg:postal-sector:77')->parentSourceId)->toBe('sg:postal-district:26')
        ->and($byId->get('sg:postal-sector:75')->parentSourceId)->toBe('sg:postal-district:27');
});

it('pins the verified planning tree of 5 regions and 55 planning areas', function (): void {
    $areas = app(SingaporeGeographyProvider::class)->addressAreaSource()->areas()->collect();

    $planningAreas = $areas->where('type', 'planning_area');

    expect($areas->where('type', 'region'))->toHaveCount(5)
        ->and($planningAreas)->toHaveCount(55)
        ->and($planningAreas->where('parentSourceId', 'sg:region:central'))->toHaveCount(22)
        ->and($planningAreas->where('parentSourceId', 'sg:region:east'))->toHaveCount(6)
        ->and($planningAreas->where('parentSourceId', 'sg:region:north'))->toHaveCount(8)
        ->and($planningAreas->where('parentSourceId', 'sg:region:north-east'))->toHaveCount(7)
        ->and($planningAreas->where('parentSourceId', 'sg:region:west'))->toHaveCount(12);

    $byId = $areas->keyBy->sourceId;

    expect($byId->get('sg:planning-area:ang-mo-kio')->parentSourceId)->toBe('sg:region:north-east')
        ->and($byId->get('sg:planning-area:changi-bay')->parentSourceId)->toBe('sg:region:east')
        ->and($byId->get('sg:planning-area:tengah')->parentSourceId)->toBe('sg:region:west')
        ->and($byId->get('sg:planning-area:singapore-river')->parentSourceId)->toBe('sg:region:central');
});

it('keeps CDC districts hierarchy-agnostic and separate from the planning tree', function (): void {
    $provider = app(SingaporeGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();

    expect($areas->where('type', 'district')->pluck('code')->sort()->values()->all())
        ->toBe(['cdc-01', 'cdc-02', 'cdc-03', 'cdc-04', 'cdc-05'])
        ->and($areas->where('type', 'planning_area')->pluck('parentSourceId')->unique()->sort()->values()->all())
        ->toBe(['sg:region:central', 'sg:region:east', 'sg:region:north', 'sg:region:north-east', 'sg:region:west']);

    $mappings = $provider->stateAreaMappings();

    expect(array_keys($mappings))->toBe(['01', '02', '03', '04', '05'])
        ->and($mappings['01'])->not->toHaveKey('hierarchy_types');

    $country = $this->seedCountry('SG');
    $provider->seed($country);

    expect(State::query()->where('country_id', $country->id)->orderBy('code')->pluck('name')->all())
        ->toBe(['Central Singapore', 'North East', 'North West', 'South East', 'South West']);
});
