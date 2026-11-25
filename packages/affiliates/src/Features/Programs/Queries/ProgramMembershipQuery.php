<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\MembershipRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgram;

final readonly class ProgramMembershipQuery implements ProgramMembershipReader
{
    public function programExists(string $programId): bool
    {
        return AffiliateProgram::query()->whereKey($programId)->exists();
    }

    public function find(string $affiliateId, string $programId): ?MembershipRecord
    {
        $membership = AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->where('program_id', $programId)
            ->whereIn('program_id', AffiliateProgram::query()->select('id'))
            ->first();

        return $membership === null ? null : $this->project($membership);
    }

    public function programIds(string $affiliateId): array
    {
        return AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('program_id', AffiliateProgram::query()->select('id'))
            ->orderBy('program_id')
            ->pluck('program_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function project(AffiliateProgramMembership $membership, bool $created = false): MembershipRecord
    {
        return new MembershipRecord(
            id: (string) $membership->getKey(),
            affiliateId: $membership->affiliate_id,
            programId: $membership->program_id,
            tierId: $membership->tier_id,
            status: $membership->status->value,
            appliedAt: $membership->applied_at?->toIso8601String(),
            approvedAt: $membership->approved_at?->toIso8601String(),
            active: $membership->isActive(),
            created: $created,
        );
    }
}
