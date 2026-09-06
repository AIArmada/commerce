<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Services;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Support\Catalog\PromotableRegistry;
use Illuminate\Support\Collection;

/**
 * Read-only snapshot of a program's promotables with fully-resolved BASE rates.
 *
 * Deterministic rules (product > category > program) are folded into
 * `effective`. Per-affiliate variable extras (volume, promotions) are listed
 * separately — never folded — so the network can't misdisplay "up to" as flat.
 *
 * Never calls CommissionRuleEngine::calculate(): that path increments
 * promotion usage. Snapshot uses getApplicableRules() + base math only.
 */
final class ProgramCatalogService
{
    public function __construct(
        private readonly CommissionRuleEngine $rules,
        private readonly PromotableRegistry $promotables,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(AffiliateProgram $program): array
    {
        // The engine is a singleton with a request-lifetime rules cache.
        // A snapshot must never serve rules cached before a merchant edit
        // (long-lived workers, or two syncs in one process).
        $this->rules->clearCache();

        $maxSubjects = max(1, (int) config('affiliates.catalog.max_subjects', 500));

        $subjects = $this->promotables->all($program->getKey(), $maxSubjects);

        if ($subjects->isEmpty()) {
            $subjects = $this->fallbackSubjectsFromRules($program, $maxSubjects);
        }

        $probe = new Affiliate;
        $probe->id = 'catalog-snapshot';

        $defaultType = $program->commission_type instanceof CommissionType
            ? $program->commission_type->value
            : (string) ($program->commission_type ?? CommissionType::Percentage->value);

        $resolved = $subjects->map(fn (array $s): array => $this->resolveSubject($program, $probe, $s, $defaultType));

        return [
            'version' => 'v1',
            'program_id' => (string) $program->getKey(),
            'currency' => $program->getAttribute('currency') ?? config('affiliates.currency.default', 'MYR'),
            'cookie_days' => $program->cookie_lifetime_days,
            'base' => [
                'commission_type' => $defaultType,
                'default_rate_bp' => (int) $program->default_commission_rate_basis_points,
            ],
            'subjects' => $resolved->values()->all(),
            'variable_extras' => [
                'volume_tiers' => $this->volumeTiers($program),
                'promotions' => $this->activePromotions($program),
            ],
        ];
    }

    /**
     * @param  array{subject_type: string, subject_key: string, title: string, url: string, amount_minor: int|null, currency: string|null, context: array<string, mixed>}  $subject
     * @return array<string, mixed>
     */
    private function resolveSubject(AffiliateProgram $program, Affiliate $probe, array $subject, string $defaultType): array
    {
        $context = array_merge($subject['context'], [
            'program_id' => $program->getKey(),
            'subject_type' => $subject['subject_type'],
            'subject_key' => $subject['subject_key'],
        ]);

        $applicable = $this->rules->getApplicableRules($probe, $context)
            ->filter(fn (AffiliateCommissionRule $r): bool => in_array($r->rule_type, [
                CommissionRuleType::Product,
                CommissionRuleType::Category,
                CommissionRuleType::Program,
            ], true));

        $winner = $applicable->first();
        $appliedIds = $winner ? [(string) $winner->getKey()] : [];

        if ($winner === null) {
            $effective = $defaultType === CommissionType::Fixed->value
                ? ['commission_type' => 'fixed', 'rate_bp' => null, 'fixed_minor' => (int) $program->default_commission_rate_basis_points]
                : ['commission_type' => 'percentage', 'rate_bp' => (int) $program->default_commission_rate_basis_points, 'fixed_minor' => null];
        } else {
            $effective = $winner->commission_type === CommissionType::Fixed
                ? ['commission_type' => 'fixed', 'rate_bp' => null, 'fixed_minor' => (int) $winner->commission_value]
                : ['commission_type' => 'percentage', 'rate_bp' => (int) $winner->commission_value, 'fixed_minor' => null];
        }

        return array_merge($subject, [
            'effective' => array_merge($effective, ['applied_rule_ids' => $appliedIds]),
            'caps' => [
                'minimum_minor' => (int) config('affiliates.commissions.minimum_minor', 0),
                'maximum_minor' => config('affiliates.commissions.maximum_minor'),
            ],
        ]);
    }

    /**
     * @return Collection<int, array{subject_type: string, subject_key: string, title: string, url: string, amount_minor: null, currency: null, context: array<string, mixed>}>
     */
    private function fallbackSubjectsFromRules(AffiliateProgram $program, int $max): Collection
    {
        $rules = AffiliateCommissionRule::query()
            ->active()->ordered()
            ->where(function ($q) use ($program): void {
                $q->whereNull('program_id')->orWhere('program_id', $program->getKey());
            })
            ->whereIn('rule_type', [CommissionRuleType::Product, CommissionRuleType::Category])
            ->limit(50)->get();

        $out = collect();

        foreach ($rules as $rule) {
            foreach ($rule->conditions ?? [] as $field => $req) {
                $keys = is_array($req) && isset($req['in']) && is_array($req['in']) ? $req['in'] : [];
                foreach ($keys as $key) {
                    $out->push([
                        'subject_type' => $rule->rule_type === CommissionRuleType::Category ? 'category' : 'product',
                        'subject_key' => (string) $key,
                        'title' => (string) $key,
                        'url' => '/',
                        'amount_minor' => null,
                        'currency' => null,
                        'context' => [$field => $key],
                    ]);
                    if ($out->count() >= $max) {
                        return $out;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function volumeTiers(AffiliateProgram $program): array
    {
        return AffiliateVolumeTier::query()
            ->where(function ($q) use ($program): void {
                $q->whereNull('program_id')->orWhere('program_id', $program->getKey());
            })
            ->orderBy('min_volume_minor')
            ->get()
            ->map(fn (AffiliateVolumeTier $t): array => [
                'min_volume_minor' => $t->min_volume_minor,
                'rate_bp' => $t->commission_rate_basis_points,
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activePromotions(AffiliateProgram $program): array
    {
        return AffiliateCommissionPromotion::query()
            ->active()
            ->where(function ($q) use ($program): void {
                $q->whereNull('program_id')->orWhere('program_id', $program->getKey());
            })
            ->get()
            ->map(fn (AffiliateCommissionPromotion $p): array => [
                'id' => (string) $p->getKey(),
                'name' => $p->name,
                'ends_at' => $p->ends_at?->toIso8601String(),
            ])->all();
    }
}
