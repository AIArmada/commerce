<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Network;

use AIArmada\AffiliateNetwork\Contracts\LinkedProgramBridge;
use AIArmada\AffiliateNetwork\Data\NetworkMembership;
use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Services\ProgramService;
use BackedEnum;

/**
 * Bridge network offers to their linked core programs.
 *
 * Joining delegates to ProgramService (idempotent, race-safe); reads are
 * plain membership queries projected to network DTOs.
 */
final class AffiliatesProgramBridge implements LinkedProgramBridge
{
    public function __construct(
        private readonly ProgramService $programs,
    ) {}

    public function join(string $affiliateId, string $programId): NetworkMembership
    {
        $affiliate = Affiliate::query()->whereKey($affiliateId)->firstOrFail();
        $program = AffiliateProgram::query()->whereKey($programId)->firstOrFail();

        $membership = $this->programs->joinProgram($affiliate, $program);

        return self::toMembership($membership);
    }

    public function membershipsFor(string $affiliateId, array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        $memberships = [];

        foreach (AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->whereIn('program_id', $programIds)
            ->get() as $membership) {
            $memberships[(string) $membership->program_id] = self::toMembership($membership);
        }

        return $memberships;
    }

    public function existingProgramIds(array $programIds): array
    {
        if ($programIds === []) {
            return [];
        }

        return AffiliateProgram::query()
            ->whereIn('id', $programIds)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function approvedProgramIds(string $affiliateId, int $limit = 500): array
    {
        return AffiliateProgramMembership::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', MembershipStatus::Approved)
            ->limit(max(1, $limit))
            ->pluck('program_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    private static function toMembership(AffiliateProgramMembership $membership): NetworkMembership
    {
        $status = $membership->status;

        return new NetworkMembership(
            id: (string) $membership->getKey(),
            affiliateId: (string) $membership->affiliate_id,
            programId: (string) $membership->program_id,
            status: $status instanceof BackedEnum ? $status->value : (string) $status,
        );
    }
}
