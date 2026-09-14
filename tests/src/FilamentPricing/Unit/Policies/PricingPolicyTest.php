<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentPricing\FilamentPricingServiceProvider;
use AIArmada\FilamentPricing\Policies\PriceListPolicy;
use AIArmada\FilamentPricing\Policies\PricePolicy;
use AIArmada\FilamentPricing\Policies\PriceTierPolicy;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Models\PriceTier;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Illuminate\Support\Facades\Gate;

uses(TestCase::class, EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();

    // The base test app does not boot every adapter provider; boot this one
    // so policy registration runs as it does in a host application.
    $provider = new FilamentPricingServiceProvider(app());
    $provider->register();
    $provider->boot();

    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);
});

it('registers owner-aware pricing policies', function (): void {
    expect(Gate::getPolicyFor(PriceList::class))->toBeInstanceOf(PriceListPolicy::class)
        ->and(Gate::getPolicyFor(Price::class))->toBeInstanceOf(PricePolicy::class)
        ->and(Gate::getPolicyFor(PriceTier::class))->toBeInstanceOf(PriceTierPolicy::class);
});

it('scopes price list access to the owning context', function (): void {
    $ownerA = User::query()->create(['name' => 'A', 'email' => 'pricing-policy-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'pricing-policy-b@example.com', 'password' => 'secret']);

    $listA = OwnerContext::withOwner($ownerA, fn (): PriceList => PriceList::query()->create([
        'name' => 'Policy List A', 'slug' => 'policy-list-a', 'currency' => 'MYR', 'is_active' => true,
    ]));
    $globalList = OwnerContext::withOwner(null, fn (): PriceList => PriceList::query()->create([
        'name' => 'Policy Global', 'slug' => 'policy-global', 'currency' => 'MYR', 'is_active' => true,
    ]));

    $policy = app(PriceListPolicy::class);

    expect(OwnerContext::withOwner($ownerA, fn (): bool => $policy->view($ownerA, $listA)))->toBeTrue()
        ->and(OwnerContext::withOwner($ownerA, fn (): bool => $policy->delete($ownerA, $listA)))->toBeTrue()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => $policy->view($ownerB, $listA)))->toBeFalse()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => $policy->update($ownerB, $listA)))->toBeFalse()
        ->and(OwnerContext::withOwner($ownerB, fn (): bool => $policy->view($ownerB, $globalList)))->toBeFalse()
        ->and($policy->viewAny($ownerA))->toBeTrue()
        ->and($policy->create($ownerA))->toBeTrue();
});
