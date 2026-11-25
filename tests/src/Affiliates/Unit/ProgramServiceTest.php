<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->service = app(ProgramService::class);

    $this->affiliate = Affiliate::create([
        'code' => 'PROG-' . uniqid(),
        'name' => 'Program Test Affiliate',
        'contact_email' => 'prog@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $this->program = AffiliateProgram::create([
        'name' => 'Test Program',
        'slug' => 'test-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
    ]);
});

describe('ProgramService', function (): void {
    describe('getAvailablePrograms', function (): void {
        test('returns active public programs', function (): void {
            $result = $this->service->getAvailablePrograms();

            expect($result)->toBeCollection();
            expect($result->pluck('id'))->toContain($this->program->id);
        });

        test('excludes inactive programs', function (): void {
            $inactiveProgram = AffiliateProgram::create([
                'name' => 'Inactive Program',
                'slug' => 'inactive-program-' . uniqid(),
                'status' => ProgramStatus::Draft,
                'visibility' => ProgramVisibility::Public,
            ]);

            $result = $this->service->getAvailablePrograms();

            expect($result->pluck('id'))->not->toContain($inactiveProgram->id);
        });

        test('excludes private programs', function (): void {
            $privateProgram = AffiliateProgram::create([
                'name' => 'Private Program',
                'slug' => 'private-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'visibility' => ProgramVisibility::Private,
            ]);

            $result = $this->service->getAvailablePrograms();

            expect($result->pluck('id'))->not->toContain($privateProgram->id);
        });
    });

    describe('joinProgram', function (): void {
        test('creates membership with approved status when no approval required', function (): void {
            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            expect($membership)->toBeInstanceOf(MembershipRecord::class);
            expect($membership->status)->toBe(MembershipStatus::Approved);
            expect($membership->approvedAt)->not->toBeNull();
        });

        test('creates membership with pending status when approval required', function (): void {
            $this->program->update(['requires_approval' => true]);

            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            expect($membership->status)->toBe(MembershipStatus::Pending);
            expect($membership->approvedAt)->toBeNull();
        });

        test('returns existing membership if already joined', function (): void {
            $first = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
            $second = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            expect($first->id)->toBe($second->id);
        });

        test('retries return the original enrollment without dispatching another join event', function (): void {
            Event::fake([AffiliateProgramJoined::class]);

            $enrollment = app(ProgramEnrollment::class);
            $first = $enrollment->join($this->affiliate->id, $this->program->id);
            $second = $enrollment->join($this->affiliate->id, $this->program->id);

            expect($first->created)->toBeTrue()
                ->and($second->created)->toBeFalse()
                ->and($second->id)->toBe($first->id);

            Event::assertDispatchedTimes(AffiliateProgramJoined::class, 1);
        });

        test('sets applied_at timestamp', function (): void {
            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            expect($membership->appliedAt)->not->toBeNull();
        });

        test('rejects cross-tenant program joins when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            $ownerA = User::query()->create([
                'name' => 'Program Owner A',
                'email' => 'program-owner-a@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Program Owner B',
                'email' => 'program-owner-b@example.com',
                'password' => 'secret',
            ]);

            $affiliate = OwnerContext::withOwner($ownerA, fn (): Affiliate => Affiliate::create([
                'code' => 'JOIN-' . uniqid(),
                'name' => 'Scoped Affiliate',
                'contact_email' => 'join@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]));

            $foreignProgram = OwnerContext::withOwner($ownerB, fn (): AffiliateProgram => AffiliateProgram::create([
                'name' => 'Foreign Program',
                'slug' => 'foreign-program-' . uniqid(),
                'status' => ProgramStatus::Active,
                'visibility' => ProgramVisibility::Public,
                'requires_approval' => false,
                'commission_type' => CommissionType::Percentage,
            ]));

            expect(fn () => OwnerContext::withOwner(
                $ownerA,
                fn (): MembershipRecord => app(ProgramEnrollment::class)->join($affiliate->id, $foreignProgram->id),
            ))->toThrow(AuthorizationException::class);
        });
    });

    describe('leaveProgram', function (): void {
        test('removes membership when exists', function (): void {
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
            app(ProgramEnrollment::class)->leave($this->affiliate->id, $this->program->id);

            expect(AffiliateProgramMembership::where([
                'affiliate_id' => $this->affiliate->id,
                'program_id' => $this->program->id,
            ])->exists())->toBeFalse();
        });

        test('does not throw if not a member', function (): void {
            app(ProgramEnrollment::class)->leave($this->affiliate->id, $this->program->id);

            expect((app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id)?->active ?? false))->toBeFalse();
        });
    });

    describe('approveMembership', function (): void {
        test('approves pending membership', function (): void {
            $this->program->update(['requires_approval' => true]);

            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
            expect($membership->status)->toBe(MembershipStatus::Pending);

            expect(app(MembershipReviewApi::class)->approve($membership->id, 'admin@example.com'))->toBeTrue();

            $membership = app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id);
            expect($membership->status)->toBe(MembershipStatus::Approved);
            expect($membership->approvedAt)->not->toBeNull();
        });
    });

    describe('upgradeTier', function (): void {
        test('throws exception if not a member', function (): void {
            $tier = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Gold',
                'level' => 3,
                'commission_rate_basis_points' => 2000,
            ]);

            $this->service->upgradeTier($this->affiliate, $this->program, $tier);
        })->throws(RuntimeException::class, 'Affiliate is not a member of this program');

        test('upgrades affiliate to new tier', function (): void {
            $tier1 = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Bronze',
                'level' => 1,
                'commission_rate_basis_points' => 1000,
            ]);

            $tier2 = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Silver',
                'level' => 2,
                'commission_rate_basis_points' => 1500,
            ]);

            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
            AffiliateProgramMembership::query()->findOrFail($membership->id)->update(['tier_id' => $tier1->id]);

            $result = $this->service->upgradeTier($this->affiliate, $this->program, $tier2);

            expect($result->tier_id)->toBe($tier2->id);
        });

        test('rejects cross-tenant tier upgrades when owner scoping is enabled', function (): void {
            config()->set('affiliates.owner.enabled', true);
            config()->set('affiliates.owner.include_global', false);

            $ownerA = User::query()->create([
                'name' => 'Tier Owner A',
                'email' => 'tier-owner-a@example.com',
                'password' => 'secret',
            ]);

            $ownerB = User::query()->create([
                'name' => 'Tier Owner B',
                'email' => 'tier-owner-b@example.com',
                'password' => 'secret',
            ]);

            [$affiliate, $program] = OwnerContext::withOwner($ownerA, function (): array {
                $affiliate = Affiliate::create([
                    'code' => 'UPGRADE-' . uniqid(),
                    'name' => 'Scoped Tier Affiliate',
                    'contact_email' => 'upgrade@example.com',
                    'status' => Active::class,
                    'commission_type' => CommissionType::Percentage,
                    'commission_rate' => 1000,
                    'currency' => 'USD',
                ]);

                $program = AffiliateProgram::create([
                    'name' => 'Scoped Program',
                    'slug' => 'scoped-program-' . uniqid(),
                    'status' => ProgramStatus::Active,
                    'visibility' => ProgramVisibility::Public,
                    'requires_approval' => false,
                    'commission_type' => CommissionType::Percentage,
                ]);

                return [$affiliate, $program];
            });

            $foreignTier = OwnerContext::withOwner($ownerB, fn (): AffiliateProgramTier => AffiliateProgramTier::create([
                'program_id' => AffiliateProgram::create([
                    'name' => 'Foreign Tier Program',
                    'slug' => 'foreign-tier-program-' . uniqid(),
                    'status' => ProgramStatus::Active,
                    'visibility' => ProgramVisibility::Public,
                    'requires_approval' => false,
                    'commission_type' => CommissionType::Percentage,
                ])->getKey(),
                'name' => 'Foreign Tier',
                'level' => 2,
                'commission_rate_basis_points' => 1500,
            ]));

            OwnerContext::withOwner($ownerA, fn (): MembershipRecord => app(ProgramEnrollment::class)->join($affiliate->id, $program->id));

            expect(fn () => OwnerContext::withOwner(
                $ownerA,
                fn (): AffiliateProgramMembership => $this->service->upgradeTier($affiliate, $program, $foreignTier),
            ))->toThrow(RuntimeException::class, 'Selected tier does not belong to the specified program.');
        });
    });

    describe('isMember', function (): void {
        test('returns true when approved member', function (): void {
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            $result = (app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id)?->active ?? false);

            expect($result)->toBeTrue();
        });

        test('returns false when not a member', function (): void {
            $result = (app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id)?->active ?? false);

            expect($result)->toBeFalse();
        });

        test('returns false when pending member', function (): void {
            $this->program->update(['requires_approval' => true]);
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            $result = (app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id)?->active ?? false);

            expect($result)->toBeFalse();
        });
    });

    describe('getMembership', function (): void {
        test('returns membership when exists', function (): void {
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            $result = app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id);

            expect($result)->toBeInstanceOf(MembershipRecord::class);
        });

        test('returns null when not a member', function (): void {
            $result = app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id);

            expect($result)->toBeNull();
        });

        test('returns the persisted tier identifier', function (): void {
            $tier = AffiliateProgramTier::create([
                'program_id' => $this->program->id,
                'name' => 'Bronze',
                'level' => 1,
                'commission_rate_basis_points' => 1000,
            ]);

            $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
            AffiliateProgramMembership::query()->findOrFail($membership->id)->update(['tier_id' => $tier->id]);

            $result = app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id);

            expect($result->tierId)->toBe($tier->id);
        });
    });

    describe('processTierUpgrades', function (): void {
        test('returns count of upgraded memberships', function (): void {
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            $result = $this->service->processTierUpgrades($this->program);

            expect($result)->toBeInt();
            expect($result)->toBeGreaterThanOrEqual(0);
        });

        test('only processes approved memberships', function (): void {
            $this->program->update(['requires_approval' => true]);
            app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);

            $result = $this->service->processTierUpgrades($this->program);

            expect($result)->toBe(0);
        });
    });

    describe('getAffiliatePrograms', function (): void {
        test('returns collection', function (): void {
            $result = $this->service->getAffiliatePrograms($this->affiliate);

            expect($result)->toBeCollection();
        });
    });
});

test('membership review cannot overwrite a completed decision', function (): void {
    $this->program->update(['requires_approval' => true]);
    $membership = app(ProgramEnrollment::class)->join($this->affiliate->id, $this->program->id);
    $review = app(MembershipReviewApi::class);

    expect($review->reject($membership->id))->toBeTrue()
        ->and($review->approve($membership->id, 'admin'))->toBeFalse()
        ->and(app(ProgramMembershipReader::class)->find($this->affiliate->id, $this->program->id)->status)->toBe(MembershipStatus::Rejected);
});
