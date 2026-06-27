<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\ProductPolicy;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    $this->policy = app(ProductPolicy::class);
});

it('allows access when owner mode is disabled', function (): void {
    config()->set('products.features.owner.enabled', false);

    expect($this->policy->view(null, new Product))->toBeTrue();
});

it('denies access when owner is set but product belongs to different owner', function (): void {
    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);

    $owner = new class extends Model
    {
        public $timestamps = false;

        public function getKey(): mixed
        {
            return 'owner-1';
        }
    };
    $product = new class extends Product
    {
        public function belongsToOwner(Model $owner): bool
        {
            return false;
        }
    };

    $result = OwnerContext::withOwner($owner, fn () => $this->policy->view(null, $product));

    expect($result)->toBeFalse();
});

it('allows access when product belongs to current owner', function (): void {
    config()->set('products.features.owner.enabled', true);

    $owner = new class extends Model
    {
        public $timestamps = false;

        public function getKey(): mixed
        {
            return 'owner-1';
        }
    };
    $product = new class extends Product
    {
        public function belongsToOwner(Model $owner): bool
        {
            return $owner->getKey() === 'owner-1';
        }
    };

    $result = OwnerContext::withOwner($owner, fn () => $this->policy->view(null, $product));

    expect($result)->toBeTrue();
});
