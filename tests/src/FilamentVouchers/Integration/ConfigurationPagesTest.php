<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Pages\StackingConfigurationPage;
use AIArmada\FilamentVouchers\Pages\TargetingConfigurationPage;
use Filament\Schemas\Schema;

uses(TestCase::class);

it('shows read-only configuration without claiming to persist changes', function (): void {
    config()->set('vouchers.stacking.mode', 'parallel');
    config()->set('vouchers.validation.check_targeting', false);

    $stacking = app(StackingConfigurationPage::class);
    $targeting = app(TargetingConfigurationPage::class);

    foreach ([$stacking, $targeting] as $page) {
        expect($page->form(Schema::make($page)))->toBeInstanceOf(Schema::class);
        expect(method_exists($page, 'save'))->toBeFalse();
        $page->mount();
    }

    expect($stacking->data['mode'])->toBe('parallel')
        ->and($targeting->data['check_targeting'])->toBeFalse();
});
