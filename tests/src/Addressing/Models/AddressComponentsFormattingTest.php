<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;

it('regenerates formatting when address components change', function (): void {
    $this->seedCountry('IT');

    $address = Address::query()->create([
        'line1' => 'Via Roma 1',
        'city' => 'Roma',
        'postcode' => '00100',
        'country_code' => 'IT',
        'components' => ['province_code' => 'RM'],
    ]);

    expect($address->fresh()?->formatted_address)->toContain('RM');

    $address->update(['components' => ['province_code' => 'MI']]);

    $fresh = $address->fresh();

    expect($fresh?->formatted_address)->toContain('MI')
        ->and($fresh?->formatted_address)->not->toContain('RM')
        ->and($fresh?->formatted_lines)->toBe(explode("\n", (string) $fresh?->formatted_address));
});
