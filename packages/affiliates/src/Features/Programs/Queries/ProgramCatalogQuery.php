<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Queries;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;
use AIArmada\Affiliates\Features\Programs\Api\ProgramSnapshot;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRulePreview;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionRuleQuote;
use AIArmada\Affiliates\Support\Catalog\PromotableRegistry;
use Illuminate\Support\Collection;

final readonly class ProgramCatalogQuery implements MerchantCatalog
{
    public function __construct(
        private CommissionRulePreview $rules,
        private PromotableRegistry $promotables,
    ) {}

    public function snapshotById(string $programId): ?ProgramSnapshot
    {
        $program = AffiliateProgram::query()->whereKey($programId)->first();

        return $program instanceof AffiliateProgram && $program->isOpen()
            ? $this->buildSnapshot($program)
            : null;
    }

    public function previewById(string $programId): ProgramSnapshot
    {
        return $this->buildSnapshot(AffiliateProgram::query()->findOrFail($programId));
    }

    public function mirrorableProgramIds(int $limit = 100): array
    {
        return AffiliateProgram::query()
            ->active()
            ->public()
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    public function availablePrograms(): array
    {
        return AffiliateProgram::query()
            ->active()
            ->public()
            ->orderBy('id')
            ->get()
            ->map(fn (AffiliateProgram $program): array => [
                'program_id' => (string) $program->getKey(),
                'name' => $program->name,
                'slug' => $program->slug,
                'status' => $program->status->value,
                'currency' => (string) ($program->getAttribute('currency') ?? config('affiliates.currency.default', 'MYR')),
                'cookie_days' => $program->cookie_lifetime_days,
            ])->all();
    }

    private function buildSnapshot(AffiliateProgram $program): ProgramSnapshot
    {
        $payload = $this->snapshot($program);

        return new ProgramSnapshot(
            programId: (string) $program->getKey(),
            currency: (string) $payload['currency'],
            payload: $payload,
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(AffiliateProgram $program): array
    {
        $maxSubjects = max(1, (int) config('affiliates.catalog.max_subjects', 500));

        $subjects = $this->promotables->all($program->getKey(), $maxSubjects);

        if ($subjects->isEmpty()) {
            $subjects = $this->fallbackSubjectsFromRules($program, $maxSubjects);
        }

        $defaultType = $program->commission_type instanceof CommissionType
            ? $program->commission_type->value
            : (string) ($program->commission_type ?? CommissionType::Percentage->value);

        $subjects = $subjects->values();
        $contexts = $subjects->map(static fn (array $subject): array => array_merge($subject['context'], [
            'subject_type' => $subject['subject_type'],
            'subject_key' => $subject['subject_key'],
        ]))->all();
        $quotes = $this->rules->previewCatalogRules((string) $program->getKey(), $contexts);
        $resolved = $subjects->map(fn (array $subject, int $index): array => $this->resolveSubject($program, $subject, $defaultType, $quotes[$index]));

        return [
            'version' => 'v2',
            'program_id' => (string) $program->getKey(),
            'currency' => $program->getAttribute('currency') ?? config('affiliates.currency.default', 'MYR'),
            'cookie_days' => $program->cookie_lifetime_days,
            'base' => [
                'commission_type' => $defaultType,
                'default_rate_bp' => (int) $program->default_commission_rate_basis_points,
            ],
            'subjects' => $resolved->values()->all(),
            'creatives' => $program->creatives()->with('media')->orderBy('id')->get()
                ->map(fn (AffiliateProgramCreative $creative): array => [
                    'id' => (string) $creative->getKey(),
                    'type' => $creative->type,
                    'name' => $creative->name,
                    'description' => $creative->description,
                    'asset_url' => $creative->getAssetUrl(),
                    'destination_url' => $creative->destination_url,
                    'width' => $creative->width,
                    'height' => $creative->height,
                    'tracking_code' => $creative->tracking_code,
                    'metadata' => $creative->metadata,
                ])->all(),
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
    private function resolveSubject(AffiliateProgram $program, array $subject, string $defaultType, ?CommissionRuleQuote $winner): array
    {
        $appliedIds = $winner === null ? [] : [$winner->id];

        if ($winner === null) {
            $effective = $defaultType === CommissionType::Fixed->value
                ? ['commission_type' => 'fixed', 'rate_bp' => null, 'fixed_minor' => (int) $program->default_commission_rate_basis_points]
                : ['commission_type' => 'percentage', 'rate_bp' => (int) $program->default_commission_rate_basis_points, 'fixed_minor' => null];
        } else {
            $effective = $winner->commissionType === CommissionType::Fixed
                ? ['commission_type' => 'fixed', 'rate_bp' => null, 'fixed_minor' => (int) $winner->value]
                : ['commission_type' => 'percentage', 'rate_bp' => (int) $winner->value, 'fixed_minor' => null];
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
                'currency' => $t->currencyCode(),
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
