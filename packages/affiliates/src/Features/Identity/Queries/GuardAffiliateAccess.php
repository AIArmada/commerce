<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Models\Affiliate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use LogicException;

final readonly class GuardAffiliateAccess implements AffiliateAccessGuard
{
    public function assertConnection(int $connectionIdentity): void
    {
        if (spl_object_id((new Affiliate)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Affiliate operations require a shared database connection.');
        }
    }

    public function assertCanWrite(string $affiliateId, int $connectionIdentity): void
    {
        $this->assertConnection($connectionIdentity);
        $this->resolve($affiliateId, false);
    }

    public function lockForWrite(string $affiliateId, int $connectionIdentity): void
    {
        $this->assertConnection($connectionIdentity);

        if ((new Affiliate)->getConnection()->transactionLevel() < 1) {
            throw new LogicException('Affiliate write locks require an active transaction.');
        }

        $this->resolve($affiliateId, true);
    }

    private function resolve(string $affiliateId, bool $lock): void
    {
        if (! Str::isUuid($affiliateId)) {
            throw (new ModelNotFoundException)->setModel(Affiliate::class, [$affiliateId]);
        }

        $query = Affiliate::query()->forOwner(includeGlobal: false)->whereKey($affiliateId);

        if ($lock) {
            $query->lockForUpdate();
        }

        if ($query->first(['id']) !== null) {
            return;
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            throw new AuthorizationException('Referenced record is not accessible in the current owner scope.');
        }

        throw (new ModelNotFoundException)->setModel(Affiliate::class, [$affiliateId]);
    }
}
