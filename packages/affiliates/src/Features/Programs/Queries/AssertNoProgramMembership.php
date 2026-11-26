<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Features\Programs\Api\MembershipRemovalGuard;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramMembership;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AssertNoProgramMembership implements MembershipRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionIdentity): void
    {
        if (spl_object_id((new AffiliateProgramMembership)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Affiliate deletion requires a shared database connection.');
        }

        if (AffiliateProgramMembership::query()->withoutGlobalScope('affiliate_owner')->where('affiliate_id', $affiliateId)->exists()) {
            throw ValidationException::withMessages(['affiliate' => 'An affiliate with linked business or historical records cannot be deleted.']);
        }
    }
}
