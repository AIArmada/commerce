<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRulePreview;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRuleQuote;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FindCommissionRules implements CommissionRulePreview
{
    /** @return Collection<int, AffiliateCommissionRule> */
    public function matchingRules(array $context): Collection
    {
        return $this->activeForProgram($context['program_id'] ?? null)
            ->filter(static fn (AffiliateCommissionRule $rule): bool => $rule->matches($context));
    }

    public function previewCatalogRules(string $programId, array $contexts): array
    {
        AffiliateProgram::query()->findOrFail($programId);

        $rules = $this->activeForProgram($programId)->filter(
            static fn (AffiliateCommissionRule $rule): bool => in_array($rule->rule_type, [
                CommissionRuleType::Product,
                CommissionRuleType::Category,
                CommissionRuleType::Program,
            ], true),
        );

        $quotes = [];

        foreach ($contexts as $context) {
            $context['program_id'] = $programId;
            $winner = $rules->first(static fn (AffiliateCommissionRule $rule): bool => $rule->matches($context));
            $quotes[] = $winner === null ? null : new CommissionRuleQuote(
                (string) $winner->getKey(),
                $winner->commission_type->value,
                $winner->commission_value,
            );
        }

        return $quotes;
    }

    /** @return Collection<int, AffiliateCommissionRule> */
    private function activeForProgram(mixed $programId): Collection
    {
        return AffiliateCommissionRule::query()
            ->active()
            ->ordered()
            ->orderBy('id')
            ->when(
                $programId,
                fn (Builder $query) => $query->where(function (Builder $inner) use ($programId): void {
                    $inner->whereNull('program_id')->orWhere('program_id', $programId);
                }),
                fn (Builder $query) => $query->whereNull('program_id')
            )
            ->get()
            ->filter(static fn (AffiliateCommissionRule $rule): bool => ! $rule->rule_type->isPerformanceBonus());
    }
}
