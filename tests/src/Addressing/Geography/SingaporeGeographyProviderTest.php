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
