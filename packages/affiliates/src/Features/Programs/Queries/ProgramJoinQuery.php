<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Enums\MembershipStatus;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReader;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinRecord;
use AIArmada\Affiliates\Features\Programs\Api\ProgramJoinReference;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

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
            $membership->joinReference($affiliate->reference, $program),
            $affiliate->code,
            $membership->approved_at?->toIso8601String(),
            $program->name,
            $program->getMorphClass(),
            $membership->status === MembershipStatus::Approved,
        );
    }

    /** @return array{AffiliateLifecycleRecord, AffiliateProgram, AffiliateProgramMembership}|null */
    private function resolve(ProgramJoinReference $reference): ?array
    {
        if (! Str::isUuid($reference->membershipId) || ! Str::isUuid($reference->programId)) {
            return null;
        }

        $connection = (new AffiliateProgramMembership)->getConnection();
        app(AffiliateAccessGuard::class)->assertConnection(spl_object_id($connection));

        if ((new AffiliateProgram)->getConnection() !== $connection) {
            throw new LogicException('Program join references require a shared database connection.');
        }

        $membership = AffiliateProgramMembership::query()->withoutGlobalScope('affiliate_owner')->find($reference->membershipId);
        $affiliate = app(AffiliateLifecycleReader::class)->find($reference->affiliate);
        $program = AffiliateProgram::query()->withoutOwnerScope()->find($reference->programId);

        if ($membership === null || $affiliate === null || $program === null) {
            return null;
        }

        self::assertReference($membership->affiliate_id, $reference->affiliate->id, 'membership affiliate_id');
        self::assertReference($membership->program_id, $reference->programId, 'membership program_id');
        self::assertReference($reference->membershipAffiliateId, $membership->affiliate_id, 'supplied membership affiliate_id');
        self::assertReference($reference->membershipProgramId, $membership->program_id, 'supplied membership program_id');
        self::assertReference($reference->tierId, $membership->tier_id, 'supplied membership tier_id');

        $canonical = $membership->joinReference($affiliate->reference, $program);

        if (! $reference->affiliate->matches($canonical->affiliate)) {
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
