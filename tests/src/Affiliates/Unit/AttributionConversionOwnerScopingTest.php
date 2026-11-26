<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionHistoryReader;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionStatisticsReader;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRemoval;
use AIArmada\Affiliates\Features\Identity\Api\ConversionBeneficiaryReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('scopes attributions to current owner and global when enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', true);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = AffiliatesTestOwner::create(['name' => 'Owner A']);
    $ownerB = AffiliatesTestOwner::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $affiliate = new Affiliate([
        'code' => 'AFF-OWNER-A',
        'name' => 'Owned Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $affiliate->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $affiliate->save();

    $global = new AffiliateAttribution([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'global-cookie',
        'cart_instance' => 'default',
    ]);
    $global->forceFill([
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $global->save();

    $ownedA = new AffiliateAttribution([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'owner-a-cookie',
        'cart_instance' => 'default',
    ]);
    $ownedA->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $ownedA->save();

    $ownedB = OwnerContext::withOwner($ownerB, function () use ($affiliate, $ownerB): AffiliateAttribution {
        $model = new AffiliateAttribution([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'cookie_value' => 'owner-b-cookie',
            'cart_instance' => 'default',
        ]);
        $model->forceFill([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ]);
        $model->save();

        return $model;
    });

    $corrupt = new AffiliateAttribution([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'corrupt-cookie',
        'cart_instance' => 'default',
    ]);
    $corrupt->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $corrupt->save();

    DB::table((new AffiliateAttribution)->getTable())
        ->where('id', $corrupt->getKey())
        ->update(['owner_id' => null]);

    $ids = AffiliateAttribution::query()->forOwner(OwnerContext::CURRENT, true)->pluck('id');

    expect($ids)->toContain($global->id)
        ->and($ids)->toContain($ownedA->id)
        ->and($ids)->not->toContain($ownedB->id)
        ->and($ids)->not->toContain($corrupt->id);
});

it('scopes conversions to current owner and global when enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', true);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = AffiliatesTestOwner::create(['name' => 'Owner A']);
    $ownerB = AffiliatesTestOwner::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $affiliate = new Affiliate([
        'code' => 'AFF-OWNER-A-2',
        'name' => 'Owned Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $affiliate->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $affiliate->save();

    $global = new AffiliateConversion([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'GLOBAL',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);
    $global->forceFill([
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $global->save();

    $ownedA = new AffiliateConversion([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'OWN-A',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);
    $ownedA->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $ownedA->save();

    $ownedB = OwnerContext::withOwner($ownerB, function () use ($affiliate, $ownerB): AffiliateConversion {
        $model = new AffiliateConversion([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => 'OWN-B',
            'total_minor' => 10000,
            'commission_minor' => 500,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
        ]);
        $model->forceFill([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ]);
        $model->save();

        return $model;
    });

    $corrupt = new AffiliateConversion([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'CORRUPT',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);
    $corrupt->forceFill([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);
    $corrupt->save();

    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $corrupt->getKey())
        ->update(['owner_id' => null]);

    $ids = AffiliateConversion::query()->forOwner(OwnerContext::CURRENT, true)->pluck('id');

    expect($ids)->toContain($global->id)
        ->and($ids)->toContain($ownedA->id)
        ->and($ids)->not->toContain($ownedB->id)
        ->and($ids)->not->toContain($corrupt->id);

    $statistics = app(ConversionStatisticsReader::class);
    $connectionIdentity = spl_object_id($affiliate->getConnection());
    expect($statistics->count([$affiliate->id], $connectionIdentity))->toBe(2)
        ->and($statistics->totals($affiliate->id, 'USD', $connectionIdentity))->toHaveCount(1)
        ->and($statistics->totals($affiliate->id, 'USD', $connectionIdentity)[0]->commissionMinor)->toBe(1000);
});

it('returns strict global-only when enabled and resolved owner is null', function (): void {
    config()->set('affiliates.owner.enabled', true);

    $owner = AffiliatesTestOwner::create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?RecordReference
        {
            return EloquentRecordReference::fromModel($this->owner);
        }
    });

    $affiliate = new Affiliate([
        'code' => 'AFF-NULL-OWNER',
        'name' => 'Owned Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);
    $affiliate->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $affiliate->save();

    $globalAttribution = OwnerContext::withOwner(null, function () use ($affiliate): AffiliateAttribution {
        $model = new AffiliateAttribution([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'cookie_value' => 'global-only-attribution',
            'cart_instance' => 'default',
        ]);
        $model->forceFill([
            'owner_type' => null,
            'owner_id' => null,
        ]);
        $model->save();

        return $model;
    });

    $record = new AffiliateAttribution([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'owned-attribution',
        'cart_instance' => 'default',
    ]);
    $record->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $record->save();

    $corruptAttribution = new AffiliateAttribution([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'corrupt-attribution',
        'cart_instance' => 'default',
    ]);
    $corruptAttribution->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $corruptAttribution->save();

    DB::table((new AffiliateAttribution)->getTable())
        ->where('id', $corruptAttribution->getKey())
        ->update(['owner_id' => null]);

    $globalConversion = OwnerContext::withOwner(null, function () use ($affiliate): AffiliateConversion {
        $model = new AffiliateConversion([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'order_reference' => 'GLOBAL',
            'total_minor' => 10000,
            'commission_minor' => 500,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
        ]);
        $model->forceFill([
            'owner_type' => null,
            'owner_id' => null,
        ]);
        $model->save();

        return $model;
    });

    $record = new AffiliateConversion([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'OWNED',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);
    $record->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $record->save();

    $corruptConversion = new AffiliateConversion([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'order_reference' => 'CORRUPT',
        'total_minor' => 10000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
    ]);
    $corruptConversion->forceFill([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]);
    $corruptConversion->save();

    DB::table((new AffiliateConversion)->getTable())
        ->where('id', $corruptConversion->getKey())
        ->update(['owner_id' => null]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?RecordReference
        {
            return null;
        }
    });

    $attributionIds = OwnerContext::withOwner(null, fn () => AffiliateAttribution::query()->forOwner()->pluck('id'));
    $conversionIds = OwnerContext::withOwner(null, fn () => AffiliateConversion::query()->forOwner()->pluck('id'));

    expect($attributionIds)->toContain($globalAttribution->id)
        ->and($attributionIds)->not->toContain($corruptAttribution->id)
        ->and($attributionIds)->toHaveCount(1);

    expect($conversionIds)->toContain($globalConversion->id)
        ->and($conversionIds)->not->toContain($corruptConversion->id)
        ->and($conversionIds)->toHaveCount(1);
});

it('protects conversion history that is not visible to the affiliate owner', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', false);
    config()->set('affiliates.commissions.auto_approve', false);

    $ownerA = AffiliatesTestOwner::create(['name' => 'Owner A']);
    $ownerB = AffiliatesTestOwner::create(['name' => 'Owner B']);

    OwnerContext::withOwner($ownerA, function () use ($ownerA, $ownerB): void {
        $affiliate = new Affiliate([
            'code' => 'AFF-HIDDEN-HISTORY',
            'name' => 'History protection',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
        $affiliate->forceFill([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ])->save();

        $reader = app(ConversionHistoryReader::class);
        $connectionIdentity = spl_object_id($affiliate->getConnection());
        expect($reader->hasAffiliateHistory($affiliate->id, $connectionIdentity))->toBeFalse();

        $conversion = new AffiliateConversion([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'commission_minor' => 500,
            'commission_currency' => 'USD',
            'status' => PendingConversion::class,
        ]);
        $conversion->forceFill([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
        ])->save();

        // Simulate a mismatched owner on historical data, without changing its affiliate reference.
        $conversion->getConnection()->table($conversion->getTable())
            ->where('id', $conversion->getKey())
            ->update(['owner_type' => $ownerB->getMorphClass(), 'owner_id' => $ownerB->getKey()]);

        expect(AffiliateConversion::query()->forOwner()->whereKey($conversion->getKey())->exists())->toBeFalse()
            ->and($reader->hasAffiliateHistory($affiliate->id, $connectionIdentity))->toBeTrue();
        expect(fn () => $reader->hasAffiliateHistory($affiliate->id, -1))->toThrow(LogicException::class);
        expect(fn () => $affiliate->delete())->toThrow(LogicException::class);
        expect(fn () => app(AffiliateRemoval::class)->delete($affiliate->id))->toThrow(ValidationException::class);

        expect(Affiliate::query()->whereKey($affiliate->id)->exists())->toBeTrue()
            ->and(AffiliateConversion::query()->withoutOwnerScope()->whereKey($conversion->getKey())->exists())->toBeTrue();
    });
});

it('aggregates conversion currencies and half-open date ranges without hydrating models', function (): void {
    config(['affiliates.owner.enabled' => false, 'affiliates.commissions.auto_approve' => false]);
    $affiliate = Affiliate::create([
        'code' => 'AFF-STATS-RANGE', 'name' => 'Range statistics', 'status' => Active::class,
        'commission_type' => 'percentage', 'commission_rate' => 500, 'currency' => 'USD',
    ]);
    $start = now()->toImmutable()->startOfDay();
    $end = $start->addDay();

    foreach ([['USD', 1000, $start], ['MYR', 3000, $start->addHour()], ['USD', 5000, $end]] as [$currency, $value, $occurredAt]) {
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id, 'affiliate_code' => $affiliate->code,
            'value_minor' => $value, 'subtotal_minor' => $value,
            'commission_minor' => 100, 'commission_currency' => $currency,
            'status' => PendingConversion::class, 'occurred_at' => $occurredAt,
        ]);
    }

    $reader = app(ConversionStatisticsReader::class);
    $connectionIdentity = spl_object_id($affiliate->getConnection());
    $rows = collect($reader->totals($affiliate->id, 'USD', $connectionIdentity, $start, $end))->keyBy('currency');

    expect($reader->count([$affiliate->id], $connectionIdentity, $start, $end))->toBe(2)
        ->and($reader->count([], $connectionIdentity))->toBe(0)
        ->and($rows->keys()->all())->toBe(['MYR', 'USD'])
        ->and($rows['USD']->revenueMinor)->toBe(1000)
        ->and($rows['MYR']->revenueMinor)->toBe(3000)
        ->and($rows['USD']->commissionMinor)->toBe(100);
    expect(fn () => $reader->count([$affiliate->id], -1))->toThrow(LogicException::class);
});

class AffiliatesTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}

it('locks conversion beneficiaries only on the shared connection and within the current owner', function (): void {
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => false, 'affiliates.owner.auto_assign_on_create' => true]);

    $ownerA = AffiliatesTestOwner::create(['name' => 'Beneficiary A']);
    $ownerB = AffiliatesTestOwner::create(['name' => 'Beneficiary B']);

    $affiliateA = OwnerContext::withOwner($ownerA, fn (): Affiliate => Affiliate::create([
        'code' => 'BENEFICIARY-A', 'name' => 'Beneficiary A', 'status' => Active::class,
        'commission_type' => 'percentage', 'commission_rate' => 500, 'currency' => 'USD',
    ]));
    $affiliateB = OwnerContext::withOwner($ownerB, fn (): Affiliate => Affiliate::create([
        'code' => 'BENEFICIARY-B', 'name' => 'Beneficiary B', 'status' => Active::class,
        'commission_type' => 'percentage', 'commission_rate' => 900, 'currency' => 'USD',
    ]));

    $connection = $affiliateA->getConnection();
    $identity = spl_object_id($connection);
    $reader = app(ConversionBeneficiaryReader::class);

    OwnerContext::withOwner($ownerA, function () use ($reader, $affiliateA, $affiliateB, $connection, $identity): void {
        expect($reader->find($affiliateB->id, $identity))->toBeNull()
            ->and($reader->find('invalid', $identity))->toBeNull()
            ->and($reader->findByCode($affiliateA->code, $identity)?->commissionRate)->toBe(500);

        expect(fn () => $reader->find($affiliateA->id, -1))->toThrow(LogicException::class);

        $connection->transaction(function () use ($reader, $affiliateA, $affiliateB, $identity): void {
            expect(fn () => $reader->lock([$affiliateA->id, $affiliateB->id], $identity))->toThrow(InvalidArgumentException::class);
            $locked = $reader->lock([$affiliateA->id, $affiliateA->id], $identity);
            expect($locked)->toHaveCount(1)
                ->and($locked[$affiliateA->id]->reference->id)->toBe($affiliateA->id)
                ->and($locked[$affiliateA->id]->commercial)->toBeTrue();
        });
    });
});
