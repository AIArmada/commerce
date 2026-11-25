<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Actions;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionAdministration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class AdministerProgramCommissions implements ProgramCommissionAdministration
{
    public function createRule(string $programId, array $data): string
    {
        return (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $data): string {
            $this->lockProgram($programId);

            return (string) AffiliateCommissionRule::query()->create([
                ...$this->ruleAttributes($data),
                'program_id' => $programId,
            ])->getKey();
        }, attempts: 3);
    }

    public function updateRule(string $programId, string $ruleId, array $data): void
    {
        (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $ruleId, $data): void {
            $this->lockProgram($programId);
            $rule = AffiliateCommissionRule::query()->where('program_id', $programId)->lockForUpdate()->findOrFail($ruleId);
            $rule->update($this->ruleAttributes(array_replace($rule->only([
                'name', 'rule_type', 'priority', 'conditions', 'commission_type', 'commission_value',
                'starts_at', 'ends_at', 'is_active', 'metadata',
            ]), $data)));
        }, attempts: 3);
    }

    public function deleteRule(string $programId, string $ruleId): bool
    {
        return (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $ruleId): bool {
            $this->lockProgram($programId);

            return (bool) AffiliateCommissionRule::query()->where('program_id', $programId)->lockForUpdate()->findOrFail($ruleId)->delete();
        }, attempts: 3);
    }

    public function createPromotion(string $programId, array $data): string
    {
        return (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $data): string {
            $this->lockProgram($programId);

            return (string) AffiliateCommissionPromotion::query()->create([
                ...$this->promotionAttributes($data),
                'program_id' => $programId,
                'current_uses' => 0,
            ])->getKey();
        }, attempts: 3);
    }

    public function updatePromotion(string $programId, string $promotionId, array $data): void
    {
        (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $promotionId, $data): void {
            $this->lockProgram($programId);
            $promotion = AffiliateCommissionPromotion::query()->where('program_id', $programId)->lockForUpdate()->findOrFail($promotionId);
            $promotion->update($this->promotionAttributes(array_replace($promotion->only([
                'name', 'description', 'bonus_type', 'bonus_value', 'conditions',
                'starts_at', 'ends_at', 'max_uses', 'affiliate_ids',
            ]), $data)));
        }, attempts: 3);
    }

    public function deletePromotion(string $programId, string $promotionId): bool
    {
        return (new AffiliateProgram)->getConnection()->transaction(function () use ($programId, $promotionId): bool {
            $this->lockProgram($programId);

            return (bool) AffiliateCommissionPromotion::query()->where('program_id', $programId)->lockForUpdate()->findOrFail($promotionId)->delete();
        }, attempts: 3);
    }

    private function lockProgram(string $programId): void
    {
        $connection = (new AffiliateProgram)->getConnection();

        foreach ([new AffiliateCommissionRule, new AffiliateCommissionPromotion, new Affiliate] as $model) {
            if ($model->getConnection() !== $connection) {
                throw new RuntimeException('Program commission administration requires a shared database connection.');
            }
        }

        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(AffiliateProgram::class, $programId);
        }

        AffiliateProgram::query()->lockForUpdate()->findOrFail($programId);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function ruleAttributes(array $data): array
    {
        $data['rule_type'] = ($data['rule_type'] ?? null) instanceof CommissionRuleType ? $data['rule_type']->value : ($data['rule_type'] ?? null);
        $data['commission_type'] = ($data['commission_type'] ?? null) instanceof CommissionType ? $data['commission_type']->value : ($data['commission_type'] ?? null);

        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'rule_type' => ['required', Rule::in(array_map(static fn (CommissionRuleType $type): string => $type->value, CommissionRuleType::programRuleCases()))],
            'priority' => ['required', 'integer'],
            'conditions' => ['nullable', 'array'],
            'commission_type' => ['required', Rule::enum(CommissionType::class)],
            'commission_value' => ['required', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', ...(! empty($data['starts_at']) ? ['after_or_equal:starts_at'] : [])],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $attributes['conditions'] ??= [];

        if ($attributes['commission_type'] === CommissionType::Percentage->value && (int) $attributes['commission_value'] > 10000) {
            throw ValidationException::withMessages(['commission_value' => 'Percentage commission must not exceed 10000 basis points.']);
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function promotionAttributes(array $data): array
    {
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bonus_type' => ['required', 'in:percentage,flat,multiplier'],
            'bonus_value' => ['required', 'integer', 'min:0'],
            'conditions' => ['nullable', 'array'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:0'],
            'affiliate_ids' => ['nullable', 'array'],
            'affiliate_ids.*' => ['required', 'string'],
        ])->validate();

        $ids = array_values(array_unique($attributes['affiliate_ids'] ?? []));
        if ($ids !== []) {
            $accessible = Affiliate::query()->forOwner(includeGlobal: (bool) config('affiliates.owner.include_global', false))
                ->whereKey($ids)->count();

            if ($accessible !== count($ids)) {
                throw ValidationException::withMessages(['affiliate_ids' => 'One or more affiliates are not accessible in the current owner scope.']);
            }
        }

        $attributes['affiliate_ids'] = $ids === [] ? null : $ids;

        return $attributes;
    }
}
