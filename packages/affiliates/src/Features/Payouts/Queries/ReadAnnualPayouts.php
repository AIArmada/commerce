<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Api\AnnualPayoutReader;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\CompletedPayout;
use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateProfileReader;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;

final class ReadAnnualPayouts implements AnnualPayoutReader
{
    public function assertSharedConnection(int $connectionIdentity): void
    {
        app(AffiliateAccessGuard::class)->assertConnection($connectionIdentity);

        if (spl_object_id((new AffiliatePayout)->getConnection()) !== $connectionIdentity) {
            throw new LogicException('Annual payout reads require a shared database connection.');
        }
    }

    public function eligibleAffiliateIds(int $year, string $currency, int $minimumMinor): array
    {
        $connection = (new AffiliatePayout)->getConnection();
        $this->assertSharedConnection(spl_object_id($connection));
        $reference = app(MerchantIdentity::class)->ownerTable();

        $eligible = $this->annualQuery($year)->whereRaw('UPPER(currency) = ?', [mb_strtoupper($currency)])
            ->select('payee_id')->groupBy('payee_id')->havingRaw('SUM(total_minor) >= ?', [$minimumMinor]);

        $query = $connection->table($reference->table)->whereIn($reference->keyColumn, $eligible);
        $config = OwnerScopeConfigFactory::fromConfig('affiliates.owner');

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            OwnerQuery::applyToQueryBuilder($query, $owner, $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal(), $config->ownerTypeColumn, $config->ownerIdColumn);
        }

        return $query->orderBy($reference->keyColumn)->pluck($reference->keyColumn)->map(static fn ($id): string => (string) $id)->values()->all();
    }

    public function total(string $affiliateId, int $year, string $currency): int
    {
        if (! Str::isUuid($affiliateId)) {
            return 0;
        }

        return (int) $this->annualQuery($year)->where('payee_id', $affiliateId)->whereRaw('UPPER(currency) = ?', [mb_strtoupper($currency)])->sum('total_minor');
    }

    public function excluded(string $affiliateId, int $year, string $currency): array
    {
        if (! Str::isUuid($affiliateId)) {
            return [];
        }

        return $this->annualQuery($year)->where('payee_id', $affiliateId)->whereRaw('UPPER(currency) != ?', [mb_strtoupper($currency)])
            ->orderBy('paid_at')->orderBy('id')->get(['reference', 'currency', 'total_minor'])
            ->map(static fn (AffiliatePayout $payout): array => ['reference' => $payout->reference, 'currency' => mb_strtoupper($payout->currency), 'amount_minor' => $payout->total_minor])->values()->all();
    }

    /** @return Builder<AffiliatePayout> */
    private function annualQuery(int $year): Builder
    {
        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();

        return AffiliatePayout::query()->where('payee_type', app(AffiliateProfileReader::class)->morphType())
            ->where('status', CompletedPayout::value())->where('paid_at', '>=', $start)->where('paid_at', '<', $start->addYear());
    }
}
