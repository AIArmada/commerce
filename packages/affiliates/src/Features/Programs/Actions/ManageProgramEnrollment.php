<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Exceptions\ProgramEnrollmentDenied;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramMembershipQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;

final readonly class ManageProgramEnrollment implements ProgramEnrollment
{
    public function __construct(private ProgramMembershipQuery $memberships, private Dispatcher $events) {}

    public function join(string $affiliateId, string $programId): MembershipRecord
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($affiliateId, $programId): MembershipRecord {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $program = $this->resolveProgram($programId);
            $this->assertSharedConnection($affiliate, $program);

            $query = AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliateId)
                ->where('program_id', $programId);

            $existing = (clone $query)->first();

            if ($existing !== null) {
                return $this->memberships->project($existing);
            }

            if (! $program->canJoin($affiliate)) {
                $existing = $query->first();

                if ($existing !== null) {
                    return $this->memberships->project($existing);
                }

                throw new ProgramEnrollmentDenied('Affiliate cannot join this program.');
            }

            $tier = $program->tiers()->withoutGlobalScope('program_owner')->first();
            $status = $program->requires_approval ? MembershipStatus::Pending : MembershipStatus::Approved;
            $now = CarbonImmutable::now();

            $membership = AffiliateProgramMembership::query()->createOrFirst([
                'affiliate_id' => $affiliateId,
                'program_id' => $programId,
            ], [
                'tier_id' => $tier?->id,
                'status' => $status,
                'applied_at' => $now,
                'approved_at' => $status === MembershipStatus::Approved ? $now : null,
            ]);

            if ($membership->wasRecentlyCreated && $status === MembershipStatus::Approved) {
                $this->events->dispatch(new AffiliateProgramJoined($affiliate, $program, $membership));
            }

            return $this->memberships->project($membership, $membership->wasRecentlyCreated);
        }, attempts: 3);
    }

    public function leave(string $affiliateId, string $programId): bool
    {
        return (new AffiliateProgramMembership)->getConnection()->transaction(function () use ($affiliateId, $programId): bool {
            $affiliate = Affiliate::query()->lockForUpdate()->findOrFail($affiliateId);
            $program = $this->resolveProgram($programId);
            $this->assertSharedConnection($affiliate, $program);

            $membership = AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliateId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return false;
            }

            $membership->delete();
            $this->events->dispatch(new AffiliateProgramLeft($affiliate, $program));

            return true;
        }, attempts: 3);
    }

    private function resolveProgram(string $programId): AffiliateProgram
    {
        return (bool) config('affiliates.owner.enabled', false)
            ? OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true)
            : AffiliateProgram::query()->findOrFail($programId);
    }

    private function assertSharedConnection(Affiliate $affiliate, AffiliateProgram $program): void
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        if ($affiliate->getConnection() !== $connection || $program->getConnection() !== $connection) {
            throw new RuntimeException('Program enrollment requires a shared database connection.');
        }
    }
}
