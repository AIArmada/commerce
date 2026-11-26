<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Queries;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use LogicException;

final class CommercialAffiliateCandidates
{
    public static function query(ConnectionInterface $connection, bool $requirePayoutEligibility = false, bool $includeGlobal = false): Builder
    {
        $reference = app(MerchantIdentity::class)->ownerTable();

        if ($reference->connectionIdentity !== spl_object_id($connection)) {
            throw new LogicException('Affiliate candidate selection requires a shared database connection.');
        }

        $query = $connection->table($reference->table)
            ->select($reference->keyColumn)
            ->where('status', Active::$name)
            ->where(static fn (Builder $nested) => $nested->whereNull('purpose')
                ->orWhereRaw('LOWER(TRIM(purpose)) = ?', ['general']));

        if ($requirePayoutEligibility) {
            $query->whereNull('paused_at')->whereNull('deactivated_at');
        }

        $config = OwnerScopeConfigFactory::fromConfig('affiliates.owner');

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            OwnerQuery::applyToQueryBuilder($query, $owner, $includeGlobal && $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal(), $config->ownerTypeColumn, $config->ownerIdColumn);
        }

        return $query;
    }
}
