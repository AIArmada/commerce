<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use AIArmada\FilamentAuthz\Models\Permission;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();
    User::query()->delete();
    Permission::query()->delete();
});

it('allows payout creation ability only for payout operators', function (): void {
    $user = User::create([
        'name' => 'Payout Creator',
        'email' => 'payout-creator@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    expect(AffiliatePayoutResource::canCreate())->toBeFalse();

    Permission::create(['name' => 'affiliate.payout', 'guard_name' => 'web']);
    $user->givePermissionTo('affiliate.payout');

    expect(AffiliatePayoutResource::canCreate())->toBeTrue();
});

it('exposes payout create route in resource pages map', function (): void {
    expect(AffiliatePayoutResource::getPages())
        ->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('create')
        ->toHaveKey('view');
});

it('persists payout records with expected payee tuple fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF-' . str()->uuid(),
        'name' => 'Payout Test Affiliate',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    AffiliatePayout::create([
        'reference' => 'PAY-' . str()->upper(str()->random(10)),
        'status' => PendingPayout::class,
        'total_minor' => 12500,
        'currency' => 'USD',
        'payee_type' => $affiliate->getMorphClass(),
        'payee_id' => $affiliate->getKey(),
    ]);

    expect(DB::table('affiliate_payouts')->where('reference', 'like', 'PAY-%')->count())->toBe(1);
});
