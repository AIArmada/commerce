<?php

declare(strict_types=1);

use AIArmada\Tax\Actions\DeleteTaxRatesAction;
use AIArmada\Tax\Actions\Exemption\DeleteTaxExemptionsAction;
use AIArmada\Tax\Actions\SaveTaxRateAction;
use AIArmada\Tax\Actions\SetTaxRatesActiveAction;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;

beforeEach(function (): void {
    config(['tax.features.owner.enabled' => false]);
});

it('rolls back a bulk rate deletion when a model event cancels a later deletion', function (): void {
    $zone = TaxZone::factory()->create();
    $rates = TaxRate::factory()->count(2)->forZone($zone)->create()->sortBy('id')->values();
    $first = $rates[0];
    $last = $rates[1];

    TaxRate::deleting(fn (TaxRate $record): ?bool => $record->is($last) ? false : null);

    expect(fn () => app(DeleteTaxRatesAction::class)->execute([$first->id, $last->id]))
        ->toThrow(RuntimeException::class, 'Tax rate deletion was cancelled.');

    expect($first->fresh())->not->toBeNull()
        ->and($last->fresh())->not->toBeNull();
});

it('rolls back a bulk exemption deletion when a model event cancels a later deletion', function (): void {
    $exemptions = TaxExemption::factory()->count(2)->create()->sortBy('id')->values();
    $first = $exemptions[0];
    $last = $exemptions[1];

    TaxExemption::deleting(fn (TaxExemption $record): ?bool => $record->is($last) ? false : null);

    expect(fn () => app(DeleteTaxExemptionsAction::class)->execute([$first->id, $last->id]))
        ->toThrow(RuntimeException::class, 'Tax exemption deletion was cancelled.');

    expect($first->fresh())->not->toBeNull()
        ->and($last->fresh())->not->toBeNull();
});

it('rolls back a bulk rate status update when a model event cancels a later update', function (): void {
    $zone = TaxZone::factory()->create();
    $rates = TaxRate::factory()->count(2)->forZone($zone)->create()->sortBy('id')->values();
    $first = $rates[0];
    $last = $rates[1];

    TaxRate::saving(fn (TaxRate $record): ?bool => $record->is($last) ? false : null);

    expect(fn () => app(SetTaxRatesActiveAction::class)->execute([$first->id, $last->id], false))
        ->toThrow(RuntimeException::class, 'Tax rate status update was cancelled.');

    expect($first->fresh()->is_active)->toBeTrue()
        ->and($last->fresh()->is_active)->toBeTrue();
});

it('does not report a cancelled rate creation as a successful save', function (): void {
    $zone = TaxZone::factory()->create();

    TaxRate::creating(fn (): bool => false);

    expect(fn () => app(SaveTaxRateAction::class)->execute([
        'zone_id' => $zone->id,
        'name' => 'Cancelled rate',
        'rate' => 600,
    ]))->toThrow(RuntimeException::class, 'Tax rate save was cancelled.');

    expect(TaxRate::query()->where('zone_id', $zone->id)->exists())->toBeFalse();
});
