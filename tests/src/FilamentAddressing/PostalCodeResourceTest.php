<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\PostalCode;
use AIArmada\FilamentAddressing\Resources\PostalCodeResource;

it('registers the postcode resource with the configured model', function (): void {
    expect(config('filament-addressing.resources.postal_codes.enabled'))->toBeTrue()
        ->and(PostalCodeResource::getModel())->toBe(PostalCode::class)
        ->and(PostalCodeResource::getNavigationLabel())->toBe('Postcodes');
});

it('respects postcode read-only configuration', function (): void {
    $original = config('filament-addressing.resources.postal_codes.read_only');

    config()->set('filament-addressing.resources.postal_codes.read_only', true);

    try {
        expect(PostalCodeResource::getPages())->not->toHaveKey('create')
            ->and(PostalCodeResource::getPages())->not->toHaveKey('edit');
    } finally {
        config()->set('filament-addressing.resources.postal_codes.read_only', $original);
    }
});
