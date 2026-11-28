<?php

declare(strict_types=1);

use AIArmada\Tax\Actions\DeleteTaxRatesAction;
use AIArmada\Tax\Actions\Exemption\ApproveExemptionsAction;
use AIArmada\Tax\Actions\Exemption\DeleteTaxExemptionsAction;
use AIArmada\Tax\Actions\Exemption\RenewTaxExemptionAction;
use AIArmada\Tax\Actions\SaveTaxRateAction;
use AIArmada\Tax\Actions\SetTaxRatesActiveAction;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

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

it('rolls back bulk approval when a later state transition is cancelled', function (): void {
    $exemptions = TaxExemption::factory()->pending()->count(2)->create()->sortBy('id')->values();
    $first = $exemptions[0];
    $last = $exemptions[1];

    TaxExemption::saving(fn (TaxExemption $record): ?bool => $record->is($last) ? false : null);

    expect(fn () => app(ApproveExemptionsAction::class)->execute([$first->id, $last->id]))
        ->toThrow(RuntimeException::class, 'Tax exemption state transition was cancelled.');

    expect($first->fresh()->isPending())->toBeTrue()
        ->and($last->fresh()->isPending())->toBeTrue()
        ->and($first->fresh()->verified_at)->toBeNull()
        ->and($last->fresh()->verified_at)->toBeNull();
});

it('rejects renewal of non-approved exemptions', function (): void {
    $pending = TaxExemption::factory()->pending()->create();
    $rejected = TaxExemption::factory()->rejected()->create();
    $revoked = TaxExemption::factory()->approved()->create();
    $revoked->revoke();

    $action = app(RenewTaxExemptionAction::class);

    foreach ([$pending, $rejected, $revoked] as $exemption) {
        expect(fn () => $action->execute($exemption->id, CarbonImmutable::today()->addYear()->toDateString()))
            ->toThrow(AuthorizationException::class);
        expect($exemption->fresh()->expires_at)->toBeNull();
    }
});

it('requires an approved exemption renewal to extend its existing expiry', function (): void {
    $expiry = CarbonImmutable::today()->addDays(30);
    $exemption = TaxExemption::factory()->approved()->create(['expires_at' => $expiry]);
    $action = app(RenewTaxExemptionAction::class);

    expect(fn () => $action->execute($exemption->id, $expiry->subDay()->toDateString()))
        ->toThrow(InvalidArgumentException::class, 'Renewal must extend the existing expiry date.');

    $action->execute($exemption->id, $expiry->addDay()->toDateString());

    expect($exemption->fresh()->expires_at->toDateString())->toBe($expiry->addDay()->toDateString());
});
