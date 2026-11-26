<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Support;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateReferenceReader;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use AIArmada\CommerceSupport\Support\OwnerScopeConfigFactory;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AffiliateReferenceProjection
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, bool $includeGlobal = false): Builder
    {
        $model = $query->getModel();
        $connection = $model->getConnection();
        $reference = app(MerchantIdentity::class)->ownerTable();

        if ($reference->connectionIdentity !== spl_object_id($connection)) {
            throw new LogicException('Affiliate projections require a shared database connection.');
        }

        $affiliates = $connection->table($reference->table)
            ->select($reference->keyColumn . ' as affiliate_reference_id', 'name as affiliate_name', 'code as affiliate_code');
        $config = OwnerScopeConfigFactory::fromConfig('affiliates.owner');

        if ($config->enabled) {
            $owner = OwnerContext::resolveConfigured($config);
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            OwnerQuery::applyToQueryBuilder($affiliates, $owner, $includeGlobal && $config->includeGlobal && ! OwnerScopeOverride::suppressIncludeGlobal(), $config->ownerTypeColumn, $config->ownerIdColumn);
        }

        return $query->select($model->qualifyColumn('*'), 'affiliate_reference.affiliate_name', 'affiliate_reference.affiliate_code')
            ->joinSub($affiliates, 'affiliate_reference', $model->qualifyColumn('affiliate_id'), '=', 'affiliate_reference.affiliate_reference_id');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function search(Builder $query, string $search, bool $byCode = false): Builder
    {
        LikeSearch::whereLike($query, $byCode ? 'affiliate_reference.affiliate_code' : 'affiliate_reference.affiliate_name', LikeSearch::contains($search));

        return $query;
    }

    /** @return array<string, string> */
    public static function options(string $search): array
    {
        /** @var array<string, string> $options */
        $options = OwnerScopeOverride::withoutIncludeGlobal(fn (): array => app(AffiliateReferenceReader::class)->options($search));

        return $options;
    }

    public static function label(string $id): ?string
    {
        /** @var string|null $label */
        $label = OwnerScopeOverride::withoutIncludeGlobal(fn (): ?string => app(AffiliateReferenceReader::class)->label($id));

        return $label;
    }
}
