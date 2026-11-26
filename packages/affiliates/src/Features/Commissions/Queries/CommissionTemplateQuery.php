<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateReader;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateSelection;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionTemplate;
use AIArmada\CommerceSupport\Support\LikeSearch;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerQuery;
use Illuminate\Database\Eloquent\Builder;

final class CommissionTemplateQuery implements CommissionTemplateReader
{
    public function search(string $search): array
    {
        return LikeSearch::whereLike($this->query(), 'name', LikeSearch::contains($search))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }

    public function find(string $id): ?CommissionTemplateSelection
    {
        $template = $this->query()->find($id);

        if ($template === null) {
            return null;
        }

        $rule = collect($template->getCommissionRules())->firstWhere('type', 'affiliate');

        return new CommissionTemplateSelection(
            (string) $template->getKey(),
            $template->name,
            $rule['commission_type'] ?? null,
            isset($rule['rate']) ? (int) $rule['rate'] : null,
        );
    }

    /** @return Builder<AffiliateCommissionTemplate> */
    private function query(): Builder
    {
        $query = AffiliateCommissionTemplate::query()->active();
        $config = AffiliateCommissionTemplate::ownerScopeConfig();

        if (! $config->enabled) {
            return $query;
        }

        $owner = OwnerContext::resolveConfigured($config);
        OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Commission template selection requires an owner context or explicit global context.');

        return OwnerQuery::applyToEloquentBuilder($query->withoutOwnerScope(), $owner, false, $config->ownerTypeColumn, $config->ownerIdColumn);
    }
}
