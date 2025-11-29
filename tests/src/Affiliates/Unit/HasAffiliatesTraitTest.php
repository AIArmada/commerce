<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Support\CartWithAffiliates;

beforeEach(function (): void {
    $this->affiliate = Affiliate::create([
        'code' => 'HAS-AFF',
        'name' => 'Trait Partner',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 300,
        'currency' => 'USD',
    ]);

    $this->cartWrapper = new CartWithAffiliates(app('cart')->getCurrentCart());
});

test('has affiliates trait reports state and records conversions', function (): void {
    $this->cartWrapper->attachAffiliate($this->affiliate->code);

    expect($this->cartWrapper->hasAffiliate())->toBeTrue();

    $conversion = $this->cartWrapper->recordAffiliateConversion([
        'subtotal' => 5_000,
        'order_reference' => 'HAS-1',
    ]);

    expect($conversion)
        ->not()->toBeNull()
        ->and($conversion->affiliateCode)->toBe($this->affiliate->code);

    $this->cartWrapper->detachAffiliate();

    expect($this->cartWrapper->hasAffiliate())->toBeFalse();
});

test('cart manager proxy preserves base cart API', function (): void {
    $manager = app('cart');

    expect($manager->instance())->toBe('default');

    $manager->setInstance('secondary')->setIdentifier('user-123');

    expect($manager->instance())->toBe('secondary')
        ->and($manager->getCurrentCart()->getIdentifier())->toBe('user-123');
});
