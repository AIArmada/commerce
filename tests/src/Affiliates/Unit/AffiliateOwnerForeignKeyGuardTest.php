<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\PayoutMethodType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\States\Active;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

it('rejects cross-tenant affiliate_id writes when owner mode is enabled', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);
    config()->set('affiliates.owner.auto_assign_on_create', false);

    $ownerA = AffiliateOwnerForeignKeyGuardTestOwner::create(['name' => 'Owner A']);
    $ownerB = AffiliateOwnerForeignKeyGuardTestOwner::create(['name' => 'Owner B']);

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

    $affiliateA = Affiliate::create([
        'code' => 'AFF-GUARD-A',
        'name' => 'Affiliate A',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $affiliateB = Affiliate::create([
        'code' => 'AFF-GUARD-B',
        'name' => 'Affiliate B',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $program = AffiliateProgram::create([
        'name' => 'Global Program',
        'slug' => 'global-program-guard',
        'status' => 'active',
        'is_public' => true,
        'requires_approval' => false,
        'default_commission_rate_basis_points' => 1000,
        'commission_type' => 'percentage',
        'cookie_lifetime_days' => 30,
    ]);

    AffiliateLink::create([
        'affiliate_id' => $affiliateA->getKey(),
        'program_id' => $program->getKey(),
        'destination_url' => 'https://example.com/a',
        'tracking_url' => 'https://example.com/t/a',
        'is_active' => true,
    ]);

    expect(fn () => AffiliateLink::create([
        'affiliate_id' => $affiliateB->getKey(),
        'program_id' => $program->getKey(),
        'destination_url' => 'https://example.com/b',
        'tracking_url' => 'https://example.com/t/b',
        'is_active' => true,
    ]))->toThrow(AuthorizationException::class);

    AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliateA->getKey(),
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'a@example.com'],
        'is_verified' => false,
        'is_default' => true,
    ]);

    expect(fn () => AffiliatePayoutMethod::create([
        'affiliate_id' => $affiliateB->getKey(),
        'type' => PayoutMethodType::PayPal,
        'details' => ['email' => 'b@example.com'],
        'is_verified' => false,
        'is_default' => true,
    ]))->toThrow(AuthorizationException::class);
});

class AffiliateOwnerForeignKeyGuardTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
