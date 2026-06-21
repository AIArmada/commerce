<?php

declare(strict_types=1);

use AIArmada\Filament\Communications\FilamentCommunicationsServiceProvider;

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[FilamentCommunicationsServiceProvider::class]))->toBeTrue();
});

test('navigation config is accessible with defaults', function (): void {
    $config = config('filament-communications');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('navigation');
    expect($config['navigation'])->toHaveKey('group');
    expect($config['navigation']['group'])->toBe('Communications');
});
