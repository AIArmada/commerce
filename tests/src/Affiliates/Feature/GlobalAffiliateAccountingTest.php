<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Conversions\Actions\AllocateUplineCommissions;
use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Actions\RecordAffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Actions\ClaimScheduledPayout;
use AIArmada\Affiliates\Features\Payouts\Actions\CreatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'affiliates.owner.enabled' => true,
        'affiliates.owner.include_global' => true,
        'affiliates.owner.auto_assign_on_create' => true,
        'affiliates.payouts.multi_level.enabled' => false,
        'affiliates.events.dispatch_conversion' => false,
        'affiliates.events.dispatch_webhooks' => false,
    ]);
});

function globalAccountingAffiliate(?string $parentId = null): Affiliate
{
    return OwnerContext::withOwner(null, fn (): Affiliate => Affiliate::create([
        'code' => 'GLOBAL-' . Str::uuid(),
        'name' => 'Global Accounting Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
        'parent_affiliate_id' => $parentId,
    ]));
}

test('global affiliate conversion accounting runs in explicit global context', function (): void {
    $owner = User::factory()->create();
    $affiliate = globalAccountingAffiliate();
    $conversion = OwnerContext::withOwner(null, fn (): AffiliateConversion => AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'GLOBAL-ACC-' . Str::uuid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]));

    OwnerContext::withOwner($owner, fn () => app(ApplyConversionAccounting::class)->handle($conversion));

    $balance = AffiliateBalance::query()->withoutGlobalScope('affiliate_owner')
        ->where('affiliate_id', $affiliate->getKey())
        ->where('currency', 'USD')
        ->firstOrFail();

    expect($balance->available_minor)->toBe(5000);
});

test('global upline allocation creates the upline leg in its own global context', function (): void {
    config([
        'affiliates.payouts.multi_level.enabled' => true,
        'affiliates.payouts.multi_level.levels' => [0.1],
    ]);

    $owner = User::factory()->create();
    $parent = globalAccountingAffiliate();
    $child = globalAccountingAffiliate((string) $parent->getKey());

    $data = new AffiliateConversionData(
        id: (string) Str::uuid(),
        affiliateId: (string) $child->getKey(),
        affiliateCode: $child->code,
        commissionMinor: 1000,
        commissionCurrency: 'USD',
        status: PendingConversion::value(),
    );

    OwnerContext::withOwner($owner, fn () => app(AllocateUplineCommissions::class)->handle(
        [$data],
        false,
        ConversionStatus::fromString(PendingConversion::value()),
        null,
    ));

    $upline = AffiliateConversion::query()->withoutOwnerScope()
        ->where('affiliate_id', $parent->getKey())
        ->where('channel', 'upline')
        ->firstOrFail();

    expect($upline->commission_minor)->toBe(100)
        ->and($upline->owner_type)->toBeNull()
        ->and($upline->owner_id)->toBeNull();
});

test('global program configuration reads require include-global or explicit global context', function (): void {
    $owner = User::factory()->create();

    $rule = OwnerContext::withOwner(null, fn (): AffiliateCommissionRule => AffiliateCommissionRule::create([
        'program_id' => null,
        'name' => 'Global rule',
        'rule_type' => CommissionRuleType::Program,
        'priority' => 0,
        'conditions' => [],
        'commission_type' => CommissionType::Percentage,
        'commission_value' => 1000,
        'is_active' => true,
    ]));

    $visible = OwnerContext::withOwner($owner, fn () => AffiliateCommissionRule::query()
        ->whereNull('program_id')
        ->pluck('id')
        ->all());

    config(['affiliates.owner.include_global' => false]);

    $hidden = OwnerContext::withOwner($owner, fn () => AffiliateCommissionRule::query()
        ->whereNull('program_id')
        ->pluck('id')
        ->all());

    expect($visible)->toContain($rule->getKey())
        ->and($hidden)->toBe([]);
});

test('recording a conversion for a global affiliate runs in explicit global context', function (): void {
    $owner = User::factory()->create();
    $affiliate = globalAccountingAffiliate();
    $cart = app('cart')->getCurrentCart();
    $cart->add('global-item', 'Order item', 10.00, 1);
    $reference = 'GLOBAL-RECORD-' . Str::uuid();

    $result = OwnerContext::withOwner($owner, fn () => app(RecordAffiliateConversion::class)->record($cart, [
        'affiliate_id' => (string) $affiliate->getKey(),
        'external_reference' => $reference,
        'commission' => 1000,
    ]));

    $conversion = AffiliateConversion::query()->withoutOwnerScope()
        ->where('external_reference', $reference)
        ->firstOrFail();

    expect($result)->not->toBeNull()
        ->and($conversion->owner_type)->toBeNull()
        ->and($conversion->owner_id)->toBeNull()
        ->and($conversion->commission_minor)->toBe(1000);
});

test('manual payout creation for a global affiliate runs in explicit global context', function (): void {
    $owner = User::factory()->create();
    $affiliate = globalAccountingAffiliate();
    $conversion = OwnerContext::withOwner(null, fn (): AffiliateConversion => AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'GLOBAL-PAYOUT-' . Str::uuid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]));
    OwnerContext::withOwner(null, fn () => app(ApplyConversionAccounting::class)->handle($conversion));

    $payout = OwnerContext::withOwner($owner, fn () => app(CreatePayout::class)->handle([$conversion->getKey()]));

    expect($payout->owner_type)->toBeNull()
        ->and($payout->owner_id)->toBeNull()
        ->and($payout->total_minor)->toBe(5000);
});

test('scheduled payout claim for a global affiliate runs in explicit global context', function (): void {
    $owner = User::factory()->create();
    $affiliate = globalAccountingAffiliate();
    $conversion = OwnerContext::withOwner(null, fn (): AffiliateConversion => AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'GLOBAL-SCHEDULED-' . Str::uuid(),
        'value_minor' => 50000,
        'commission_minor' => 5000,
        'commission_currency' => 'USD',
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]));
    OwnerContext::withOwner(null, fn () => app(ApplyConversionAccounting::class)->handle($conversion));

    $operation = OwnerContext::withOwner($owner, fn () => app(ClaimScheduledPayout::class)->handle((string) $affiliate->getKey(), 0, 'USD'));

    expect($operation)->not->toBeNull()
        ->and($operation->owner_type)->toBeNull()
        ->and($operation->owner_id)->toBeNull();
});
