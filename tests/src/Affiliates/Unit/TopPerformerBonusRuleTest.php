<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Rules\TopPerformerBonusRule;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

it('keeps top performer bonuses inside the current owner scope', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.bonuses.top_performer.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.bonuses.top_performer.min_revenue', 0);
    config()->set('affiliates.bonuses.top_performer.positions', [1 => 1000]);

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

    $bonuses = OwnerContext::withOwner($ownerA, fn (): array => (new TopPerformerBonusRule)->calculate(
        CarbonImmutable::now()->startOfMonth(),
        CarbonImmutable::now()->endOfMonth(),
    ));

    expect($bonuses)->toHaveCount(1)
        ->and($bonuses[0]['affiliate_id'])->toBe($affiliateA->id)
        ->and($bonuses[0]['affiliate_id'])->not->toBe($affiliateB->id);
});

it('fails closed without an owner context when owner mode is enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.bonuses.top_performer.enabled', true);
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    expect(fn () => (new TopPerformerBonusRule)->calculate(
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
