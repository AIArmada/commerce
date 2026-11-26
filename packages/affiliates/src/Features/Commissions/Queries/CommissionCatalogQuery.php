<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Queries;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Features\Commissions\Api\CommissionCatalogReader;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Programs\Api\ProgramReferenceReader;

final readonly class CommissionCatalogQuery implements CommissionCatalogReader
{
    public function __construct(private ProgramReferenceReader $programs) {}

    /**
     * @return list<array{subject_type: string, subject_key: string, title: string, url: string, amount_minor: null, currency: null, context: array<string, mixed>}>
     */
    public function fallbackSubjects(string $programId, int $max): array
    {
        $this->programs->assertReadable($programId);
        $max = max(1, $max);

        $rules = AffiliateCommissionRule::query()
            ->active()->ordered()
            ->where(function ($q) use ($programId): void {
                $q->whereNull('program_id')->orWhere('program_id', $programId);
            })
            ->whereIn('rule_type', [CommissionRuleType::Product, CommissionRuleType::Category])
            ->orderBy('id')->limit(50)->get();

        $out = [];
        $seen = [];

        foreach ($rules as $rule) {
            foreach ($rule->conditions ?? [] as $field => $req) {
                $keys = is_array($req) && isset($req['in']) && is_array($req['in']) ? $req['in'] : [];
                foreach ($keys as $key) {
                    if (! is_string($key) && ! is_int($key)) {
                        continue;
                    }

                    $identity = $rule->rule_type->value . ':' . (string) $key;
                    if (isset($seen[$identity])) {
                        continue;
                    }
                    $seen[$identity] = true;
                    $out[] = [
                        'subject_type' => $rule->rule_type === CommissionRuleType::Category ? 'category' : 'product',
                        'subject_key' => (string) $key,
                        'title' => (string) $key,
                        'url' => '/',
                        'amount_minor' => null,
                        'currency' => null,
                        'context' => [$field => $key],
                    ];
                    if (count($out) >= $max) {
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
    public function activePromotions(string $programId): array
    {
        $this->programs->assertReadable($programId);

        return AffiliateCommissionPromotion::query()
            ->active()
            ->where(function ($q) use ($programId): void {
                $q->whereNull('program_id')->orWhere('program_id', $programId);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (AffiliateCommissionPromotion $p): array => [
                'id' => (string) $p->getKey(),
                'name' => $p->name,
                'ends_at' => $p->ends_at?->toIso8601String(),
            ])->all();
    }
}
