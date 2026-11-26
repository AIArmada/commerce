<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Commissions\Api\PerformanceBonusCalculation;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Settings\AffiliateBonusSettings;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function topPerformerRuleSettings(array $overrides): void
{
    AffiliateBonusSettings::fake($overrides);
}

it('keeps top performer bonuses inside the current owner scope', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    topPerformerRuleSettings([
        'topPerformerEnabled' => true,
        'topPerformerMinRevenue' => 0,
        'topPerformerMinRevenueCurrency' => 'USD',
        'topPerformerPositions' => [1 => 1000],
    ]);

    $ownerA = TopPerformerBonusRuleTestOwner::create(['name' => 'Owner A']);
    $ownerB = TopPerformerBonusRuleTestOwner::create(['name' => 'Owner B']);

    $affiliateA = OwnerContext::withOwner($ownerA, fn (): Affiliate => Affiliate::create([
        'code' => 'TOP-A',
        'name' => 'Affiliate A',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]));

    $affiliateB = OwnerContext::withOwner($ownerB, fn (): Affiliate => Affiliate::create([
        'code' => 'TOP-B',
        'name' => 'Affiliate B',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]));

    foreach ([[$ownerA, $affiliateA, 'A'], [$ownerB, $affiliateB, 'B']] as [$owner, $affiliate, $suffix]) {
        OwnerContext::withOwner($owner, function () use ($affiliate, $suffix): void {
            AffiliateConversion::create([
                'commission_currency' => 'USD',
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'TOP-' . $suffix,
                'subtotal_minor' => 10000,
                'total_minor' => 10000,
                'value_minor' => 10000,
                'commission_minor' => 1000,
                'status' => ApprovedConversion::class,
                'occurred_at' => now(),
            ]);
        });
    }

    $bonuses = OwnerContext::withOwner($ownerA, fn (): array => app(PerformanceBonusCalculation::class)->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer->value,
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    ));

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliateA->id)
        ->and($bonuses[0]['affiliate_id'])->not->toBe($affiliateB->id);
});

it('honors the caller include global flag instead of overriding it from config', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    topPerformerRuleSettings([
        'topPerformerEnabled' => true,
        'topPerformerMinRevenue' => 0,
        'topPerformerMinRevenueCurrency' => 'USD',
        'topPerformerPositions' => [1 => 1000, 2 => 500],
    ]);

    $owner = TopPerformerBonusRuleTestOwner::create(['name' => 'Scoped Owner']);

    $scopedAffiliate = OwnerContext::withOwner($owner, fn (): Affiliate => Affiliate::create([
        'code' => 'TOP-SCOPED',
        'name' => 'Scoped Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]));
    $globalAffiliate = OwnerContext::withOwner(null, fn (): Affiliate => Affiliate::create([
        'code' => 'TOP-GLOBAL',
        'name' => 'Global Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]));

    OwnerContext::withOwner($owner, fn () => AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $scopedAffiliate->id,
        'affiliate_code' => $scopedAffiliate->code,
        'order_reference' => 'TOP-SCOPED-ORDER',
        'subtotal_minor' => 10000,
        'total_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]));
    OwnerContext::withOwner(null, fn () => AffiliateConversion::create([
        'commission_currency' => 'USD',
        'affiliate_id' => $globalAffiliate->id,
        'affiliate_code' => $globalAffiliate->code,
        'order_reference' => 'TOP-GLOBAL-ORDER',
        'subtotal_minor' => 20000,
        'total_minor' => 20000,
        'value_minor' => 20000,
        'commission_minor' => 2000,
        'status' => ApprovedConversion::class,
        'occurred_at' => now(),
    ]));

    $engine = app(PerformanceBonusCalculation::class);
    $from = CarbonImmutable::now()->startOfMonth();
    $to = CarbonImmutable::now()->endOfMonth();

    $scoped = OwnerContext::withOwner($owner, fn (): array => $engine->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer->value,
        $from,
        $to,
        false,
    ));
    $withGlobal = OwnerContext::withOwner($owner, fn (): array => $engine->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer->value,
        $from,
        $to,
        true,
    ));

    expect($scoped)->toHaveCount(1)
        ->and($scoped[0]['affiliate_id'])->toBe($scopedAffiliate->id)
        ->and($withGlobal)->toHaveCount(2)
        ->and($withGlobal[0]['affiliate_id'])->toBe($globalAffiliate->id);

    $suppressed = OwnerContext::withOwner($owner, fn (): mixed => OwnerScopeOverride::withoutIncludeGlobal(
        fn (): array => $engine->calculatePerformanceBonuses(CommissionRuleType::TopPerformer->value, $from, $to, true),
    ));

    expect($suppressed)->toHaveCount(1)
        ->and($suppressed[0]['affiliate_id'])->toBe($scopedAffiliate->id);
});

it('fails closed without an owner context when owner mode is enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    topPerformerRuleSettings(['topPerformerEnabled' => true]);
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    expect(fn () => app(PerformanceBonusCalculation::class)->calculatePerformanceBonuses(
        CommissionRuleType::TopPerformer->value,
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    ))->toThrow(RuntimeException::class, 'owner context or explicit global context');
});

final class TopPerformerBonusRuleTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
