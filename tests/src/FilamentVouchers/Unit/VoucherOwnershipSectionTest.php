<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentVouchers\Resources\VoucherResource\Schemas\VoucherForm;
use Filament\Schemas\Schema;

uses(TestCase::class);

function voucherFormSectionHeadings(): array
{
    $schema = VoucherForm::configure(Schema::make());

    return collect($schema->getComponents())
        ->map(fn ($component): ?string => method_exists($component, 'getHeading') ? $component->getHeading() : null)
        ->filter()
        ->values()
        ->all();
}

it('hides the ownership section while owner mode is off', function (): void {
    config()->set('vouchers.owner.enabled', false);

    expect(voucherFormSectionHeadings())->not->toContain('Ownership');
});

it('shows the ownership section when owner mode is on', function (): void {
    config()->set('vouchers.owner.enabled', true);
    config()->set('filament-vouchers.owners', [
        ['model' => User::class, 'label' => 'User'],
    ]);

    expect(voucherFormSectionHeadings())->toContain('Ownership');
});
