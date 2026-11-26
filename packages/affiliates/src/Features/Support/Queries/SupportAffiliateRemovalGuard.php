<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Queries;

use AIArmada\Affiliates\Contracts\AffiliateRemovalGuard;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupportAffiliateRemovalGuard implements AffiliateRemovalGuard
{
    public function assertCanRemove(string $affiliateId, int $connectionInstanceId): void
    {
        $model = new AffiliateSupportTicket;

        if (spl_object_id($model->getConnection()) !== $connectionInstanceId) {
            throw new LogicException('Affiliate removal requires a shared database connection.');
        }

        if ($model->newQueryWithoutScopes()->where('affiliate_id', $affiliateId)->exists()) {
            throw ValidationException::withMessages(['affiliate' => 'An affiliate with historical records cannot be deleted.']);
        }
    }
}
