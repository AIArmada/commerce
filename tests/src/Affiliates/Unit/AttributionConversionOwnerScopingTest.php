<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        public function resolve(): ?Model
        {
            return $this->owner;
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

        public function resolve(): ?Model
        {
            return $this->owner;
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
});

it('returns strict global-only when enabled and resolved owner is null', function (): void {
    config()->set('affiliates.owner.enabled', true);

    $owner = AffiliatesTestOwner::create(['name' => 'Owner']);

    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
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
        public function resolve(): ?Model
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

class AffiliatesTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
