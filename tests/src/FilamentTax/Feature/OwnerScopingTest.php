<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

uses(TestCase::class);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentTax\Actions\DownloadTaxExemptionCertificateAction;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function bindOwnerResolverForFilamentTax(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('scopes filament tax resources and badges to the current owner', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-tax-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-tax-owner-b@example.com',
        'password' => 'secret',
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA',
        'is_active' => true,
    ]);

    $classA = TaxClass::query()->create([
        'name' => 'Standard A',
        'slug' => 'standard-a',
        'is_active' => true,
    ]);

    TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classA->id,
        'reason' => 'A',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    bindOwnerResolverForFilamentTax($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB',
        'is_active' => true,
    ]);

    $classB = TaxClass::query()->create([
        'name' => 'Standard B',
        'slug' => 'standard-b',
        'is_active' => true,
    ]);

    TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classB->id,
        'reason' => 'B',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    expect(TaxExemptionResource::getNavigationBadge())->toBe('1');

    expect(TaxZoneResource::getEloquentQuery()->whereKey($zoneA->id)->exists())->toBeTrue()
        ->and(TaxZoneResource::getEloquentQuery()->whereKey($zoneB->id)->exists())->toBeFalse();
});

it('prevents cross-tenant certificate downloads', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    Storage::fake('local');

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-tax-download-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-tax-download-owner-b@example.com',
        'password' => 'secret',
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    $classA = TaxClass::query()->create([
        'name' => 'Standard A',
        'slug' => 'standard-a-download',
        'is_active' => true,
    ]);

    Storage::disk('local')->put('tax-exemptions/a.pdf', 'A');

    $exemptionA = TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classA->id,
        'reason' => 'A',
        'status' => 'approved',
        'document_path' => 'tax-exemptions/a.pdf',
    ]);

    bindOwnerResolverForFilamentTax($ownerB);

    $classB = TaxClass::query()->create([
        'name' => 'Standard B',
        'slug' => 'standard-b-download',
        'is_active' => true,
    ]);

    Storage::disk('local')->put('tax-exemptions/b.pdf', 'B');

    $exemptionB = TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $classB->id,
        'reason' => 'B',
        'status' => 'approved',
        'document_path' => 'tax-exemptions/b.pdf',
    ]);

    bindOwnerResolverForFilamentTax($ownerA);

    $action = app(DownloadTaxExemptionCertificateAction::class);

    expect($action->execute($exemptionA))->toBeInstanceOf(StreamedResponse::class);

    expect(fn (): StreamedResponse => $action->execute($exemptionB))
        ->toThrow(NotFoundHttpException::class);
});

it('uses owner write guard to block global tax classes on tenant write paths', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);

    $owner = User::query()->create([
        'name' => 'Owner Guard Tenant',
        'email' => 'filament-tax-owner-write-guard@example.com',
        'password' => 'secret',
    ]);

    bindOwnerResolverForFilamentTax($owner);

    $ownedClass = TaxClass::query()->create([
        'name' => 'Owned Class',
        'slug' => 'owned-write-guard-class',
        'is_active' => true,
    ]);

    $globalClass = OwnerContext::withOwner(null, fn (): TaxClass => TaxClass::query()->create([
        'name' => 'Global Class',
        'slug' => 'global-write-guard-class',
        'is_active' => true,
    ]));

    expect(OwnerWriteGuard::findOrFailForOwner(TaxClass::class, $ownedClass->id, includeGlobal: false))
        ->toBeInstanceOf(TaxClass::class)
        ->and(fn () => OwnerWriteGuard::findOrFailForOwner(TaxClass::class, $globalClass->id, includeGlobal: false))
        ->toThrow(AuthorizationException::class);
});
