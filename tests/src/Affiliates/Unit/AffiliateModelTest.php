<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\AffiliateStatus;
use AIArmada\Affiliates\Models\Affiliate;

test('Affiliate scopeForOwner filters by owner when enabled', function (): void {
    config(['affiliates.owner.enabled' => true]);

    $owner1 = new class extends \Illuminate\Database\Eloquent\Model {
        public function getMorphClass() { return 'User'; }
        public function getKey() { return 1; }
    };

    $owner2 = new class extends \Illuminate\Database\Eloquent\Model {
        public function getMorphClass() { return 'User'; }
        public function getKey() { return 2; }
    };

    $affiliate1 = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate 1',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => 'User',
        'owner_id' => 1,
    ]);

    $affiliate2 = Affiliate::create([
        'code' => 'AFF2',
        'name' => 'Test Affiliate 2',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
        'owner_type' => 'User',
        'owner_id' => 2,
    ]);

    $results = Affiliate::forOwner($owner1)->pluck('id');

    expect($results)->toContain($affiliate1->id);
    expect($results)->not->toContain($affiliate2->id);
});

test('Affiliate scopeForOwner returns all when disabled', function (): void {
    config(['affiliates.owner.enabled' => false]);

    $affiliate1 = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate 1',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $affiliate2 = Affiliate::create([
        'code' => 'AFF2',
        'name' => 'Test Affiliate 2',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    $results = Affiliate::forOwner()->pluck('id');

    expect($results)->toContain($affiliate1->id);
    expect($results)->toContain($affiliate2->id);
});

test('Affiliate isActive returns true for active status', function (): void {
    $affiliate = new Affiliate(['status' => AffiliateStatus::Active]);

    expect($affiliate->isActive())->toBeTrue();
});

test('Affiliate isActive returns false for non-active status', function (): void {
    $affiliate = new Affiliate(['status' => AffiliateStatus::Pending]);

    expect($affiliate->isActive())->toBeFalse();
});

test('Affiliate has attributions relationship', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    expect($affiliate->attributions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Affiliate has conversions relationship', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFF1',
        'name' => 'Test Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ]);

    expect($affiliate->conversions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('Affiliate has parent relationship', function (): void {
    $affiliate = new Affiliate();

    expect($affiliate->parent())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('Affiliate has children relationship', function (): void {
    $affiliate = new Affiliate();

    expect($affiliate->children())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});