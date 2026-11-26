<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Actions;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Events\AffiliateProgramLeft;
use AIArmada\Affiliates\Exceptions\ProgramEnrollmentDenied;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramEligibilityQuery;
use AIArmada\Affiliates\Features\Programs\Queries\ProgramMembershipQuery;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

final readonly class ManageProgramEnrollment implements ProgramEnrollment
{
    public function __construct(private ProgramMembershipQuery $memberships, private ProgramEligibilityQuery $eligibility, private Dispatcher $events) {}

    public function join(string $affiliateId, string $programId): MembershipRecord
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        return $connection->transaction(function () use ($affiliateId, $programId, $connection): MembershipRecord {
            $affiliate = app(AffiliateProfileReader::class)->lock($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = $this->resolveProgram($programId);
            $this->assertSharedConnection($program);

            $query = AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliateId)
                ->where('program_id', $programId);

            $existing = (clone $query)->first();

            if ($existing !== null) {
                return $this->memberships->project($existing);
            }

            if ($this->eligibility->eligibleIds($affiliate, [$program]) === []) {
                $existing = $query->first();

                if ($existing !== null) {
                    return $this->memberships->project($existing);
                }

                throw new ProgramEnrollmentDenied('Affiliate cannot join this program.');
            }

            $tier = AffiliateProgramTier::query()->where('program_id', $program->getKey())->orderBy('level')->withoutGlobalScope('program_owner')->first();
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
                $joined = $membership->joinReference($affiliate->reference, $program);
                $connection->afterCommit(fn () => $this->events->dispatch(new AffiliateProgramJoined($joined)));
            }

            return $this->memberships->project($membership, $membership->wasRecentlyCreated);
        }, attempts: 3);
    }

    public function leave(string $affiliateId, string $programId): bool
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        return $connection->transaction(function () use ($affiliateId, $programId, $connection): bool {
            $affiliate = app(AffiliateProfileReader::class)->lock($affiliateId, spl_object_id((new AffiliateProgramMembership)->getConnection()))
                ?? throw new ModelNotFoundException('Affiliate not found.');
            $program = $this->resolveProgram($programId);
            $this->assertSharedConnection($program);

            $membership = AffiliateProgramMembership::query()
                ->where('affiliate_id', $affiliateId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return false;
            }

            $membership->delete();
            $affiliateReferenceId = $affiliate->reference->id;
            $programId = (string) $program->getKey();
            $membershipId = (string) $membership->getKey();
            $connection->afterCommit(fn () => $this->events->dispatch(new AffiliateProgramLeft($affiliateReferenceId, $programId, $membershipId)));

            return true;
        }, attempts: 3);
    }

    private function resolveProgram(string $programId): AffiliateProgram
    {
        if (! config('affiliates.owner.enabled', false)) {
            return AffiliateProgram::query()->sharedLock()->findOrFail($programId);
        }

        $program = OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId, includeGlobal: true);

        return AffiliateProgram::query()->withoutOwnerScope()
            ->where('owner_type', $program->owner_type)
            ->where('owner_id', $program->owner_id)
            ->sharedLock()->findOrFail($programId);
    }

    private function assertSharedConnection(AffiliateProgram $program): void
    {
        $connection = (new AffiliateProgramMembership)->getConnection();

        if ($program->getConnection() !== $connection || (new AffiliateProgramTier)->getConnection() !== $connection) {
            throw new RuntimeException('Program enrollment requires a shared database connection.');
        }
    }
}
