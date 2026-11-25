<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionAdministration;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

test('program commission administration pins program and protects consumed promotion usage', function (): void {
    $first = AffiliateProgram::create(['name' => 'First', 'slug' => 'first-commission-admin', 'status' => ProgramStatus::Active, 'commission_type' => 'percentage']);
    $second = AffiliateProgram::create(['name' => 'Second', 'slug' => 'second-commission-admin', 'status' => ProgramStatus::Active, 'commission_type' => 'percentage']);
    $api = app(ProgramCommissionAdministration::class);
    $ruleData = ['name' => 'Rule', 'rule_type' => 'program', 'priority' => 10, 'commission_type' => 'percentage', 'commission_value' => 1000];

    expect(fn () => $api->createRule($first->id, [...$ruleData, 'commission_value' => 10001]))->toThrow(ValidationException::class);
    $ruleId = $api->createRule($first->id, [...$ruleData, 'program_id' => $second->id]);
    $api->updateRule($first->id, $ruleId, ['program_id' => $second->id, 'name' => 'Updated']);
    expect(AffiliateCommissionRule::findOrFail($ruleId)->program_id)->toBe($first->id);
    expect(fn () => $api->deleteRule($second->id, $ruleId))->toThrow(ModelNotFoundException::class);
    expect($api->deleteRule($first->id, $ruleId))->toBeTrue();

    $promotionId = $api->createPromotion($first->id, [
        'name' => 'Promo', 'bonus_type' => 'flat', 'bonus_value' => 100,
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'current_uses' => 999,
    ]);
    $promotion = AffiliateCommissionPromotion::findOrFail($promotionId);
    expect($promotion->current_uses)->toBe(0);
    $promotion->increment('current_uses');
    $api->updatePromotion($first->id, $promotionId, ['current_uses' => 0, 'program_id' => $second->id, 'name' => 'Updated promo']);
    expect($promotion->fresh()->current_uses)->toBe(1)
        ->and($promotion->fresh()->program_id)->toBe($first->id);
    expect(fn () => $api->deletePromotion($second->id, $promotionId))->toThrow(ModelNotFoundException::class);
    expect($api->deletePromotion($first->id, $promotionId))->toBeTrue();
});
