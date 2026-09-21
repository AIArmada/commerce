<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;

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
