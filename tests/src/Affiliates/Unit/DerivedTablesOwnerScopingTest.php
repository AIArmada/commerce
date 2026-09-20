<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

it('scopes touchpoints, daily stats, and network to current owner', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = DerivedTablesOwnerScopingTestOwner::create(['name' => 'Owner A']);
    $ownerB = DerivedTablesOwnerScopingTestOwner::create(['name' => 'Owner B']);

    $setOwner = function (?Model $owner): void {
        app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
        {
            public function __construct(
                private readonly ?Model $owner,
            ) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
    };

    $setOwner($ownerA);

    $affiliateA = new Affiliate([
        'code' => 'AFF-OWN-A',
        'name' => 'Affiliate A',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $affiliateA->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $affiliateA->save();

    $setOwner($ownerB);

    $affiliateB = new Affiliate([
        'code' => 'AFF-OWN-B',
        'name' => 'Affiliate B',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $affiliateB->forceFill([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);
    $affiliateB->save();

    $setOwner($ownerA);

    $record = new AffiliateTouchpoint([
        'affiliate_attribution_id' => fake()->uuid(),
        'affiliate_id' => $affiliateA->getKey(),
        'affiliate_code' => $affiliateA->code,
        'source' => 'facebook',
        'touched_at' => now(),
    ]);
    $record->forceFill([
        'owner_type' => $affiliateA->owner_type,
        'owner_id' => $affiliateA->owner_id,
    ]);
    $record->save();

    $setOwner($ownerB);

    $record = new AffiliateTouchpoint([
        'affiliate_attribution_id' => fake()->uuid(),
        'affiliate_id' => $affiliateB->getKey(),
        'affiliate_code' => $affiliateB->code,
        'source' => 'google',
        'touched_at' => now(),
    ]);
    $record->forceFill([
        'owner_type' => $affiliateB->owner_type,
        'owner_id' => $affiliateB->owner_id,
    ]);
    $record->save();

    $setOwner($ownerA);

    $record = new AffiliateDailyStat([
        'affiliate_id' => $affiliateA->getKey(),
        'date' => Carbon::parse('2025-01-01')->toDateString(),
        'currency' => 'USD',
        'clicks' => 1,
        'unique_clicks' => 1,
        'attributions' => 0,
        'conversions' => 0,
        'revenue_cents' => 0,
        'commission_cents' => 0,
        'refunds' => 0,
        'refund_amount_cents' => 0,
        'conversion_rate' => 0,
        'epc_cents' => 0,
    ]);
    $record->forceFill([
        'owner_type' => $affiliateA->owner_type,
        'owner_id' => $affiliateA->owner_id,
    ]);
    $record->save();

    $setOwner($ownerB);

    $record = new AffiliateDailyStat([
        'affiliate_id' => $affiliateB->getKey(),
        'date' => Carbon::parse('2025-01-01')->toDateString(),
        'currency' => 'USD',
        'clicks' => 1,
        'unique_clicks' => 1,
        'attributions' => 0,
        'conversions' => 0,
        'revenue_cents' => 0,
        'commission_cents' => 0,
        'refunds' => 0,
        'refund_amount_cents' => 0,
        'conversion_rate' => 0,
        'epc_cents' => 0,
    ]);
    $record->forceFill([
        'owner_type' => $affiliateB->owner_type,
        'owner_id' => $affiliateB->owner_id,
    ]);
    $record->save();

    $setOwner($ownerA);

    AffiliateUpline::addToUpline($affiliateA, null);

    $setOwner($ownerB);

    AffiliateUpline::addToUpline($affiliateB, null);

    $setOwner($ownerA);

    expect(AffiliateTouchpoint::query()->count())->toBe(1)
        ->and(AffiliateDailyStat::query()->count())->toBe(1)
        ->and(AffiliateUpline::query()->where('descendant_id', $affiliateA->getKey())->count())->toBeGreaterThan(0)
        ->and(AffiliateUpline::query()->where('descendant_id', $affiliateB->getKey())->count())->toBe(0);

    $setOwner($ownerB);

    expect(AffiliateTouchpoint::query()->count())->toBe(1)
        ->and(AffiliateDailyStat::query()->count())->toBe(1)
        ->and(AffiliateUpline::query()->where('descendant_id', $affiliateB->getKey())->count())->toBeGreaterThan(0)
        ->and(AffiliateUpline::query()->where('descendant_id', $affiliateA->getKey())->count())->toBe(0);
});

final class DerivedTablesOwnerScopingTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
