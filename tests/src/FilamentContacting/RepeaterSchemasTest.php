<?php

declare(strict_types=1);

use AIArmada\FilamentContacting\Features\ContactMethods\EntryPoints\Filament\Schemas\ContactMethodRepeaterSchema;
use AIArmada\FilamentContacting\Features\SocialProfiles\EntryPoints\Filament\Schemas\SocialProfileRepeaterSchema;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;

test('social profile and contact method repeater schemas build repeaters and normalize rows', function (): void {
    expect(SocialProfileRepeaterSchema::make())->toBeInstanceOf(Repeater::class)
        ->and(ContactMethodRepeaterSchema::make())->toBeInstanceOf(Repeater::class);

    $saved = ContactMethodRepeaterSchema::normalizeRowsForSave([
        'type' => 'phone',
        'phone_value' => ' +60 12-345 6789 ',
    ]);

    expect($saved['value'])->toBe('+60 12-345 6789')
        ->and(array_key_exists('phone_value', $saved))->toBeFalse();

    $filled = ContactMethodRepeaterSchema::normalizeRowsForFill($saved);
    expect($filled['phone_value'])->toBe('+60 12-345 6789');

    $compared = ContactMethodRepeaterSchema::normalizeRowsForComparison($saved);
    expect($compared['phone_value'])->toBe('+60123456789')
        ->and(ContactMethodRepeaterSchema::itemLabel(['type' => 'email', 'value' => 'admin@example.com']))
        ->toBe('Email: admin@example.com');
});

test('social profile repeater exposes the built profile URL as a new-tab link', function (): void {
    $placeholder = collect(SocialProfileRepeaterSchema::make()->getDefaultChildComponents())
        ->first(fn ($component): bool => $component instanceof Placeholder && $component->getKey(false) === 'profile_url');

    expect($placeholder)->toBeInstanceOf(Placeholder::class)
        ->and($placeholder->getUrl('https://www.facebook.com/example'))->toBe('https://www.facebook.com/example')
        ->and($placeholder->shouldOpenUrlInNewTab())->toBeTrue();
});
