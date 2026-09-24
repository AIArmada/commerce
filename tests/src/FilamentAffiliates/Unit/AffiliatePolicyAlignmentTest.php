<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Policies\AffiliateConversionPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateFraudSignalPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateLinkPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliatePayoutPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateProgramPolicy;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliateConversion::query()->delete();
    AffiliateFraudSignal::query()->delete();
    AffiliatePayout::query()->delete();
    AffiliateProgram::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

function alignmentUser(string $email, string ...$permissions): User
{
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $user = User::create([
        'name' => 'Alignment User',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function alignmentAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'AFF-' . Str::uuid(),
        'name' => 'Alignment Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
}

it('lets payout operators view the payout list and create payouts', function (): void {
    $policy = new AffiliatePayoutPolicy;

    $operator = alignmentUser('payout-operator@example.com', 'affiliates.payout.update');

    expect($policy->viewAny($operator))->toBeTrue()
        ->and($policy->view($operator, new AffiliatePayout))->toBeTrue();

    $legacy = alignmentUser('legacy-payout@example.com', 'affiliate.payout');

    expect($policy->viewAny($legacy))->toBeTrue()
        ->and($policy->create($legacy))->toBeTrue();

    $creator = alignmentUser('payout-creator@example.com', 'affiliates.payout.create');

    expect($policy->create($creator))->toBeTrue();

    $this->actingAs($creator);

    expect(AffiliatePayoutResource::canCreate())->toBeTrue();

    $nobody = alignmentUser('payout-nobody@example.com');

    expect($policy->viewAny($nobody))->toBeFalse()
        ->and($policy->create($nobody))->toBeFalse();
});

it('lets affiliate approvers view fraud signals', function (): void {
    $policy = new AffiliateFraudSignalPolicy;

    $affiliate = alignmentAffiliate();
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);
    $signal = AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->getKey(),
        'conversion_id' => $conversion->getKey(),
        'rule_code' => 'velocity',
        'risk_points' => 80,
        'severity' => FraudSeverity::Critical,
        'description' => 'Velocity abuse detected',
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    $reviewer = alignmentUser('fraud-reviewer@example.com', 'affiliate.approve');

    expect($policy->viewAny($reviewer))->toBeTrue()
        ->and($policy->view($reviewer, $signal))->toBeTrue();

    $nobody = alignmentUser('fraud-nobody@example.com');

    expect($policy->viewAny($nobody))->toBeFalse()
        ->and($policy->view($nobody, $signal))->toBeFalse();
});

it('lets conversion updaters view conversions', function (): void {
    $policy = new AffiliateConversionPolicy;

    $affiliate = alignmentAffiliate();
    $conversion = AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-' . Str::uuid(),
        'subject_key' => 'checkout:' . Str::uuid(),
        'status' => PendingConversion::class,
        'occurred_at' => now(),
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
    ]);

    $updater = alignmentUser('conversion-updater@example.com', 'affiliate_conversion.update');

    expect($policy->viewAny($updater))->toBeTrue()
        ->and($policy->view($updater, $conversion))->toBeTrue();

    $approver = alignmentUser('conversion-approver@example.com', 'affiliate.approve');

    expect($policy->viewAny($approver))->toBeTrue()
        ->and($policy->view($approver, $conversion))->toBeTrue();
});

it('lets generic affiliate staff pass resource-specific policies', function (): void {
    $program = AffiliateProgram::create([
        'name' => 'Alignment Program',
        'slug' => 'alignment-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1200,
        'cookie_lifetime_days' => 30,
    ]);

    $staff = alignmentUser(
        'generic-staff@example.com',
        'affiliate.viewAny',
        'affiliate.view',
        'affiliate.create',
        'affiliate.update',
        'affiliate.delete',
    );

    expect((new AffiliateProgramPolicy)->viewAny($staff))->toBeTrue()
        ->and((new AffiliateProgramPolicy)->view($staff, $program))->toBeTrue()
        ->and((new AffiliateProgramPolicy)->create($staff))->toBeTrue()
        ->and((new AffiliateProgramPolicy)->update($staff, $program))->toBeTrue()
        ->and((new AffiliateProgramPolicy)->delete($staff, $program))->toBeTrue()
        ->and((new AffiliateLinkPolicy)->viewAny($staff))->toBeTrue();

    $scoped = alignmentUser('scoped-staff@example.com', 'affiliate-program.viewAny');

    expect((new AffiliateProgramPolicy)->viewAny($scoped))->toBeTrue()
        ->and((new AffiliateProgramPolicy)->create($scoped))->toBeFalse();
});
