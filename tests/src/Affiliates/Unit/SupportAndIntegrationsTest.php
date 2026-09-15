<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Services\AffiliateReportService;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;

// VoucherIntegrationRegistrar Tests
test('VoucherIntegrationRegistrar can be instantiated', function (): void {
    $registrar = app(VoucherIntegrationRegistrar::class);
    expect($registrar)->toBeInstanceOf(VoucherIntegrationRegistrar::class);
});

// WebhookDispatcher Tests
test('WebhookDispatcher can be instantiated', function (): void {
    $dispatcher = app(WebhookDispatcher::class);
    expect($dispatcher)->toBeInstanceOf(WebhookDispatcher::class);
});

// ProgramService Tests
test('ProgramService leaveProgram removes membership', function (): void {
    $service = app(ProgramService::class);

    $affiliate = Affiliate::create([
        'code' => 'LEAVE001',
        'name' => 'Leave Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Leave Program',
        'slug' => 'leave-program',
        'description' => 'Leave program description',
        'status' => 'active',
        'commission_type' => 'percentage',
        'default_commission_rate_basis_points' => 1000,
        'visibility' => ProgramVisibility::Public,
    ]);

    // First join
    $service->joinProgram($affiliate, $program);
    expect($service->isMember($affiliate, $program))->toBeTrue();

    // Then leave
    $service->leaveProgram($affiliate, $program);
    expect($service->isMember($affiliate->fresh(), $program))->toBeFalse();
});

// AffiliateReportService Tests
test('AffiliateReportService can be instantiated', function (): void {
    $service = app(AffiliateReportService::class);
    expect($service)->toBeInstanceOf(AffiliateReportService::class);
});

// AffiliateCommissionRule Model Tests
test('AffiliateCommissionRule can be created', function (): void {
    $rule = AffiliateCommissionRule::create([
        'name' => 'Test Rule',
        'rule_type' => 'product',  // valid CommissionRuleType value
        'commission_type' => 'percentage',
        'commission_value' => 1000,
        'is_active' => true,
        'priority' => 1,
        'conditions' => ['min_amount' => 5000],
    ]);

    expect($rule)->toBeInstanceOf(AffiliateCommissionRule::class);
    expect($rule->name)->toBe('Test Rule');
    expect($rule->is_active)->toBeTrue();
});

test('AffiliateCommissionRule getTable returns configured table', function (): void {
    $rule = new AffiliateCommissionRule;
    expect($rule->getTable())->toBeString();
});
