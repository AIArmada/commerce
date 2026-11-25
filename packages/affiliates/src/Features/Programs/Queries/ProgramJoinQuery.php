<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReference;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use InvalidArgumentException;

final class ProgramJoinQuery implements ProgramJoinReader
{
    public function find(ProgramJoinReference $reference): ?ProgramJoinRecord
    {
        $resolved = $this->resolve($reference);

        if ($resolved === null) {
            return null;
        }

        [$affiliate, $program, $membership] = $resolved;

        return new ProgramJoinRecord(
            $membership->joinReference($affiliate, $program),
            $affiliate->code,
            $membership->approved_at?->toIso8601String(),
        );
    }

    /** @return array{Affiliate, AffiliateProgram, AffiliateProgramMembership}|null */
    public function resolve(ProgramJoinReference $reference): ?array
    {
        $membership = AffiliateProgramMembership::query()->withoutGlobalScope('affiliate_owner')->find($reference->membershipId);
        $affiliate = Affiliate::query()->withoutOwnerScope()->find($reference->affiliate->id);
        $program = AffiliateProgram::query()->withoutOwnerScope()->find($reference->programId);

        if ($membership === null || $affiliate === null || $program === null) {
            return null;
        }

        self::assertReference($membership->affiliate_id, $reference->affiliate->id, 'membership affiliate_id');
        self::assertReference($membership->program_id, $reference->programId, 'membership program_id');
        self::assertReference($reference->membershipAffiliateId, $membership->affiliate_id, 'supplied membership affiliate_id');
        self::assertReference($reference->membershipProgramId, $membership->program_id, 'supplied membership program_id');
        self::assertReference($reference->tierId, $membership->tier_id, 'supplied membership tier_id');

        $canonical = $membership->joinReference($affiliate, $program);

        if ($reference->affiliate != $canonical->affiliate) {
            throw new InvalidArgumentException('Trusted affiliate owner or identity does not match the canonical source.');
        }

        if ($reference->programOwnerType !== $canonical->programOwnerType || $reference->programOwnerId !== $canonical->programOwnerId) {
            throw new InvalidArgumentException('Trusted affiliate program owner does not match the canonical source.');
        }

        return [$affiliate, $program, $membership];
    }

    private static function assertReference(?string $supplied, ?string $canonical, string $what): void
    {
        if ($supplied !== $canonical) {
            throw new InvalidArgumentException(sprintf('Trusted affiliate %s does not match the canonical source.', $what));
        }
    }
}
