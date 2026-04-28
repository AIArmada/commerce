<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Actions\UpdateAffiliateFraudSignalStatus;
use AIArmada\FilamentAuthz\Models\Permission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    AffiliateFraudSignal::query()->delete();
    Affiliate::query()->delete();
});

it('updates a fraud signal status with reviewed metadata inside the current owner scope', function (): void {
    $owner = User::create([
        'name' => 'Fraud Action Owner',
        'email' => 'fraud-action-owner@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($owner, function () use ($owner): void {
        Permission::firstOrCreate(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);
        $owner->givePermissionTo('affiliates.fraud.update');
    });

    $this->actingAs($owner);

    $signal = OwnerContext::withOwner($owner, function (): AffiliateFraudSignal {
        $affiliate = Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Scoped Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        return AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'velocity',
            'risk_points' => 80,
            'severity' => FraudSeverity::Critical,
            'description' => 'Velocity abuse detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
    });

    OwnerContext::withOwner($owner, function () use ($signal, $owner): void {
        UpdateAffiliateFraudSignalStatus::run($signal, FraudSignalStatus::Dismissed);

        $signal->refresh();

        expect($signal->status)->toBe(FraudSignalStatus::Dismissed)
            ->and($signal->reviewed_by)->toBe((string) $owner->getAuthIdentifier())
            ->and($signal->reviewed_at)->not->toBeNull();
    });
});

it('blocks cross-tenant fraud signal status updates', function (): void {
    $ownerA = User::create([
        'name' => 'Fraud Action Owner A',
        'email' => 'fraud-action-owner-a@example.com',
        'password' => 'secret',
    ]);

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        Permission::firstOrCreate(['name' => 'affiliates.fraud.update', 'guard_name' => 'web']);
        $ownerA->givePermissionTo('affiliates.fraud.update');
    });

    $this->actingAs($ownerA);

    $ownerB = User::create([
        'name' => 'Fraud Action Owner B',
        'email' => 'fraud-action-owner-b@example.com',
        'password' => 'secret',
    ]);

    $signal = OwnerContext::withOwner($ownerB, function (): AffiliateFraudSignal {
        $affiliate = Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Other Tenant Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);

        return AffiliateFraudSignal::create([
            'affiliate_id' => $affiliate->getKey(),
            'rule_code' => 'pattern',
            'risk_points' => 65,
            'severity' => FraudSeverity::High,
            'description' => 'Pattern mismatch detected',
            'status' => FraudSignalStatus::Detected,
            'detected_at' => now(),
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($signal): void {
        expect(fn () => UpdateAffiliateFraudSignalStatus::run($signal, FraudSignalStatus::Confirmed))
            ->toThrow(ModelNotFoundException::class);
    });
});
