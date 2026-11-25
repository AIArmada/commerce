<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Programs\Api\MembershipAdministration;
use AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi;
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierAdministration;
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierManagement;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\States\Active;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

describe('AffiliateProgramMembership Model', function (): void {
    beforeEach(function (): void {
        $this->program = AffiliateProgram::create([
            'name' => 'Membership Program ' . uniqid(),
            'slug' => 'membership-program-' . uniqid(),
            'status' => ProgramStatus::Active,
            'is_public' => true,
            'requires_approval' => true,
            'default_commission_rate_basis_points' => 500,
            'commission_type' => CommissionType::Percentage,
            'cookie_lifetime_days' => 30,
        ]);

        $this->affiliate = Affiliate::create([
            'code' => 'MEMBER' . uniqid(),
            'name' => 'Membership Test Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $this->tier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Bronze',
            'level' => 1,
            'commission_rate_basis_points' => 500,
            'min_conversions' => 0,
            'min_revenue' => 0,
        ]);
    });

    test('can be created with required fields', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership)->toBeInstanceOf(AffiliateProgramMembership::class);
        expect($membership->status)->toBe(MembershipStatus::Pending);
    });

    test('belongs to affiliate', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->affiliate)->toBeInstanceOf(Affiliate::class);
        expect($membership->affiliate->id)->toBe($this->affiliate->id);
    });

    test('belongs to program', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->program)->toBeInstanceOf(AffiliateProgram::class);
        expect($membership->program->id)->toBe($this->program->id);
    });

    test('belongs to tier', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
        ]);

        expect($membership->tier)->toBeInstanceOf(AffiliateProgramTier::class);
        expect($membership->tier->id)->toBe($this->tier->id);
    });

    test('rejects a tier from another program even when owner scoping is disabled', function (): void {
        config(['affiliates.owner.enabled' => false]);

        $otherProgram = $this->program->replicate();
        $otherProgram->slug = 'other-program-' . uniqid();
        $otherProgram->save();

        $otherTier = $this->tier->replicate();
        $otherTier->program_id = $otherProgram->id;
        $otherTier->save();

        $attributes = [
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ];

        expect(fn () => AffiliateProgramMembership::create([
            ...$attributes,
            'tier_id' => $otherTier->id,
        ]))->toThrow(AuthorizationException::class);

        $membership = AffiliateProgramMembership::create([
            ...$attributes,
            'tier_id' => $this->tier->id,
        ]);

        expect(fn () => $membership->update(['tier_id' => $otherTier->id]))
            ->toThrow(AuthorizationException::class);

        expect($membership->refresh()->tier_id)->toBe($this->tier->id);
    });

    test('isActive returns true when approved and not expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subDay(),
            'approved_at' => now(),
        ]);

        expect($membership->isActive())->toBeTrue();
    });

    test('isActive returns false when pending', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns false when rejected', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Rejected,
            'applied_at' => now(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns false when expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subMonth(),
            'approved_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        expect($membership->isActive())->toBeFalse();
    });

    test('isActive returns true when not yet expired', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now()->subMonth(),
            'approved_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
        ]);

        expect($membership->isActive())->toBeTrue();
    });

    test('approve method updates status and timestamp', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        app(MembershipReviewApi::class)->approve((string) $membership->getKey(), 'admin@example.com');

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Approved);
        expect($membership->approved_at)->not->toBeNull();
        expect($membership->approved_by)->toBe('admin@example.com');
    });

    test('approve method works without approver', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        app(MembershipReviewApi::class)->approve((string) $membership->getKey());

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Approved);
        expect($membership->approved_by)->toBeNull();
    });

    test('reject method updates status', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        app(MembershipReviewApi::class)->reject((string) $membership->getKey());

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Rejected);
    });

    test('suspend method updates status', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'approved_at' => now(),
        ]);

        expect(app(MembershipAdministration::class)->suspend($membership->id))->toBeTrue();
        expect(app(MembershipAdministration::class)->suspend($membership->id))->toBeFalse();

        $membership->refresh();
        expect($membership->status)->toBe(MembershipStatus::Suspended);
    });

    test('administers memberships without changing their affiliate or program identity', function (): void {
        $api = app(MembershipAdministration::class);
        $created = $api->create($this->affiliate->id, $this->program->id, [
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Approved,
        ]);

        expect($created->status)->toBe(MembershipStatus::Approved)
            ->and($created->approvedAt)->not->toBeNull();

        $updated = $api->update($created->id, [
            'affiliate_id' => 'untrusted-affiliate',
            'program_id' => 'untrusted-program',
            'status' => MembershipStatus::Suspended,
        ]);

        expect($updated->affiliateId)->toBe($this->affiliate->id)
            ->and($updated->programId)->toBe($this->program->id)
            ->and($updated->status)->toBe(MembershipStatus::Suspended);

        $api->delete($created->id);

        expect(AffiliateProgramMembership::query()->whereKey($created->id)->exists())->toBeFalse();
    });

    test('tier administration removes membership references without deleting members', function (): void {
        $membership = app(MembershipAdministration::class)->create($this->affiliate->id, $this->program->id, [
            'tier_id' => $this->tier->id,
        ]);

        app(ProgramTierAdministration::class)->delete($this->tier->id);

        expect(AffiliateProgramMembership::query()->findOrFail($membership->id)->tier_id)->toBeNull()
            ->and(AffiliateProgramTier::query()->whereKey($this->tier->id)->exists())->toBeFalse();
    });

    test('program administration owns creation, updates and deletion', function (): void {
        $api = app(ProgramAdministration::class);
        $programId = $api->create(['name' => 'Managed program']);
        $api->update($programId, ['name' => 'Renamed program']);

        expect(AffiliateProgram::query()->findOrFail($programId)->name)->toBe('Renamed program');

        $api->delete($programId);

        expect(AffiliateProgram::query()->whereKey($programId)->exists())->toBeFalse();
    });

    test('upgradeTier method updates tier', function (): void {
        $silverTier = AffiliateProgramTier::create([
            'program_id' => $this->program->id,
            'name' => 'Silver',
            'level' => 2,
            'commission_rate_basis_points' => 750,
            'min_conversions' => 10,
            'min_revenue' => 100000,
        ]);

        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'tier_id' => $this->tier->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'approved_at' => now(),
        ]);

        app(ProgramTierManagement::class)->upgrade($membership->affiliate_id, $membership->program_id, $silverTier->id);

        $membership->refresh();
        expect($membership->tier_id)->toBe($silverTier->id);
    });

    test('can store custom_terms array', function (): void {
        $customTerms = [
            'special_rate' => 1200,
            'bonus_percentage' => 5,
            'exclusive_products' => ['product-a', 'product-b'],
        ];

        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => now(),
            'custom_terms' => $customTerms,
        ]);

        expect($membership->custom_terms)->toBeArray();
        expect($membership->custom_terms['special_rate'])->toBe(1200);
        expect($membership->custom_terms['bonus_percentage'])->toBe(5);
    });

    test('casts status as enum', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Pending,
            'applied_at' => now(),
        ]);

        expect($membership->status)->toBeInstanceOf(MembershipStatus::class);
    });

    test('casts dates correctly', function (): void {
        $membership = AffiliateProgramMembership::create([
            'affiliate_id' => $this->affiliate->id,
            'program_id' => $this->program->id,
            'status' => MembershipStatus::Approved,
            'applied_at' => '2024-06-01 10:00:00',
            'approved_at' => '2024-06-02 14:00:00',
            'expires_at' => '2025-06-01 23:59:59',
        ]);

        expect($membership->applied_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($membership->approved_at)->toBeInstanceOf(CarbonImmutable::class);
        expect($membership->expires_at)->toBeInstanceOf(CarbonImmutable::class);
    });

    test('uses correct table name from config', function (): void {
        $membership = new AffiliateProgramMembership;

        expect($membership->getTable())->toBe(config('affiliates.database.tables.program_memberships', 'affiliate_program_memberships'));
    });
});
