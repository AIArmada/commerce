<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAffiliates\Concerns\InteractsWithAffiliate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    AffiliateConversion::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
});

// Helper class to test the trait
class TestInteractsWithAffiliateClass
{
    use InteractsWithAffiliate;
}

it('returns null affiliate when user is not authenticated', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->getAffiliate())->toBeNull();
});

it('hasAffiliate returns false when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->hasAffiliate())->toBeFalse();
});

it('getConversions returns empty collection when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;
    $conversions = $testClass->getConversions();

    expect($conversions)
        ->toBeInstanceOf(Collection::class)
        ->toBeEmpty();
});

it('getPayouts returns empty collection when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;
    $payouts = $testClass->getPayouts();

    expect($payouts)
        ->toBeInstanceOf(Collection::class)
        ->toBeEmpty();
});

it('getTotalEarnings returns zero when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->getTotalEarnings())->toBe(0);
});

it('getPendingEarnings returns zero when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->getPendingEarnings())->toBe(0);
});

it('getTotalClicks returns zero when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->getTotalClicks())->toBe(0);
});

it('getTotalConversions returns zero when no affiliate', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->getTotalConversions())->toBe(0);
});

it('formatAmount formats with USD by default', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    // 10000 cents = $100.00
    $formatted = $testClass->formatAmount(10000, 'USD');

    expect($formatted)->toBe('USD 100.00');
});

it('formatAmount handles zero decimal currencies', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    // JPY has no decimal places
    $formatted = $testClass->formatAmount(1000, 'JPY');

    expect($formatted)->toBe('JPY 1,000');
});

it('formatAmount handles MYR currency', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    $formatted = $testClass->formatAmount(5000, 'MYR');

    expect($formatted)->toBe('MYR 50.00');
});

it('formatAmount handles KRW zero decimal currency', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    $formatted = $testClass->formatAmount(50000, 'KRW');

    expect($formatted)->toBe('KRW 50,000');
});

it('formatAmount handles VND zero decimal currency', function (): void {
    $testClass = new TestInteractsWithAffiliateClass;

    $formatted = $testClass->formatAmount(100000, 'VND');

    expect($formatted)->toBe('VND 100,000');
});

it('resolves affiliate by contact email when owner scope uses a tenant owner', function (): void {
    config()->set('affiliates.owner.enabled', true);

    $tenantOwner = InteractsWithAffiliateTestOwner::create(['name' => 'Tenant Owner']);

    app()->instance(OwnerResolverInterface::class, new class($tenantOwner) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $user = User::create([
        'name' => 'Portal User',
        'email' => 'portal-user-' . Str::uuid() . '@example.com',
        'password' => 'secret',
    ]);

    Affiliate::create([
        'code' => 'OWN-' . Str::uuid(),
        'name' => 'Tenant Scoped Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'contact_email' => $user->email,
        'owner_type' => $tenantOwner->getMorphClass(),
        'owner_id' => (string) $tenantOwner->getKey(),
    ]);

    $this->actingAs($user);

    $testClass = new TestInteractsWithAffiliateClass;

    expect($testClass->hasAffiliate())->toBeTrue()
        ->and($testClass->getAffiliate()?->contact_email)->toBe($user->email);
});

class InteractsWithAffiliateTestOwner extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'test_products';

    protected $guarded = [];

    protected $keyType = 'string';
}
