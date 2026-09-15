<?php

declare(strict_types=1);

use AIArmada\FilamentCashierChip\CustomerPortal\Pages\BillingDashboard;
use Filament\Pages\Page;

it('has the expected billing dashboard structure', function (): void {
    $reflection = new ReflectionClass(BillingDashboard::class);

    expect(is_subclass_of(BillingDashboard::class, Page::class))->toBeTrue()
        ->and($reflection->hasProperty('navigationIcon'))->toBeTrue()
        ->and($reflection->hasProperty('slug'))->toBeTrue()
        ->and($reflection->hasMethod('getHeaderWidgets'))->toBeTrue()
        ->and($reflection->hasMethod('getFooterWidgets'))->toBeTrue()
        ->and($reflection->hasMethod('getTitle'))->toBeTrue()
        ->and($reflection->hasMethod('getNavigationLabel'))->toBeTrue();
});
