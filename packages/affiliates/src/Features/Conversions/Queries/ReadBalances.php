<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Conversions\Api\BalanceReader;
use AIArmada\Affiliates\Features\Conversions\Api\BalanceSnapshot;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use LogicException;

final readonly class ReadBalances implements BalanceReader
{
    public function find(string $affiliateId, string $currency): ?BalanceSnapshot
    {
        return $this->forId($affiliateId)->where('currency', mb_strtoupper($currency))->first()?->toSnapshot();
    }

    public function lock(string $affiliateId, string $currency, int $connectionIdentity): ?BalanceSnapshot
    {
        $connection = (new AffiliateBalance)->getConnection();

        if ($connection->transactionLevel() < 1 || spl_object_id($connection) !== $connectionIdentity) {
            throw new LogicException('Balance locking requires the shared active accounting transaction.');
        }

        return $this->forId($affiliateId)->where('currency', mb_strtoupper($currency))->lockForUpdate()->first()?->toSnapshot();
    }

    public function forAffiliate(string $affiliateId): array
    {
        return $this->forId($affiliateId)->orderBy('currency')->get()->map(
            static fn (AffiliateBalance $balance): BalanceSnapshot => $balance->toSnapshot(),
        )->values()->all();
    }

    public function hasRecords(string $affiliateId): bool
    {
        return $this->forId($affiliateId)->exists();
    }

    public function canRequestPayout(string $affiliateId, ?string $currency = null): bool
    {
        return $this->forId($affiliateId)
            ->when($currency !== null, static fn (Builder $query) => $query->where('currency', mb_strtoupper((string) $currency)))
            ->where('available_minor', '>', 0)
            ->whereColumn('available_minor', '>=', 'minimum_payout_minor')
            ->exists();
    }

    public function payoutCandidates(int $minimum, ?string $affiliateId = null): iterable
    {
        $model = new AffiliateBalance;
        $connection = $model->getConnection();
        $reference = app(MerchantIdentity::class)->ownerTable();

        if ($reference->connectionIdentity !== spl_object_id($connection)) {
            throw new LogicException('Payout candidate selection requires the affiliate database connection.');
        }

        $eligible = $connection->table($reference->table)
            ->select($reference->keyColumn)
            ->where('status', Active::$name)
            ->where(static fn (QueryBuilder $query) => $query->whereNull('purpose')
                ->orWhereRaw('LOWER(TRIM(purpose)) = ?', ['general']));

        $query = AffiliateBalance::query()
            ->where('available_minor', $minimum > 0 ? '>=' : '>', max(0, $minimum))
            ->whereIn($model->qualifyColumn('affiliate_id'), $eligible);

        if ($affiliateId !== null) {
            if (! Str::isUuid($affiliateId)) {
                return;
            }

            $query->where('affiliate_id', $affiliateId);
        }

        foreach ($query->lazyById(100) as $balance) {
            yield $balance->toSnapshot();
        }
    }

    /** @return Builder<AffiliateBalance> */
    private function forId(string $affiliateId): Builder
    {
        return Str::isUuid($affiliateId)
            ? AffiliateBalance::query()->where('affiliate_id', $affiliateId)
            : AffiliateBalance::query()->whereRaw('1 = 0');
    }
}
