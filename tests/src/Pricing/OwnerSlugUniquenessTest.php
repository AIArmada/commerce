<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Models\Price;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Pricing\Tests\Concerns\EnsuresPricingSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

uses(EnsuresPricingSchema::class);

beforeEach(function (): void {
    $this->ensurePricingSchema();
});

if (! function_exists('bindSlugPricingOwner')) {
    function bindSlugPricingOwner(?Model $owner): void
    {
        app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
        {
            public function __construct(private ?Model $owner) {}

            public function resolve(): ?Model
            {
                return $this->owner;
            }
        });
    }
}

function createSlugPricingOwner(string $name, string $email): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'secret',
    ]);
}

it('allows the same slug under different owners on the canonical schema', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.database.tables.price_lists', 'slug_price_lists');

    Schema::dropIfExists('slug_price_lists');

    $migration = require __DIR__ . '/../../../packages/pricing/database/migrations/2000_12_01_000001_create_price_lists_table.php';
    $migration->up();

    try {
        $ownerA = createSlugPricingOwner('Slug Owner A', 'slug-owner-a@example.com');
        $ownerB = createSlugPricingOwner('Slug Owner B', 'slug-owner-b@example.com');

        bindSlugPricingOwner($ownerA);

        $listA = OwnerContext::withOwner($ownerA, fn (): PriceList => PriceList::query()->create([
            'name' => 'Retail A',
            'slug' => 'retail',
        ]));

        $listB = OwnerContext::withOwner($ownerB, fn (): PriceList => PriceList::query()->create([
            'name' => 'Retail B',
            'slug' => 'retail',
        ]));

        expect($listA->getKey())->not->toBe($listB->getKey());

        expect(fn () => OwnerContext::withOwner($ownerA, fn (): PriceList => PriceList::query()->create([
            'name' => 'Retail A Duplicate',
            'slug' => 'retail',
        ])))->toThrow(UniqueConstraintViolationException::class);
    } finally {
        Schema::dropIfExists('slug_price_lists');
    }
});

it('keeps price list and price ensure calls idempotent', function (): void {
    config()->set('pricing.features.owner.enabled', true);

    bindSlugPricingOwner(null);

    $owner = createSlugPricingOwner('Ensure Owner', 'ensure-owner@example.com');

    $ids = OwnerContext::withOwner(null, function () use ($owner): array {
        $first = PriceList::query()->createOrFirst(
            ['slug' => 'ensure-idempotent'],
            ['name' => 'Ensure Idempotent'],
        );

        $second = PriceList::query()->createOrFirst(
            ['slug' => 'ensure-idempotent'],
            ['name' => 'Ensure Idempotent'],
        );

        $priceAttributes = [
            'price_list_id' => $first->getKey(),
            'priceable_type' => $owner->getMorphClass(),
            'priceable_id' => $owner->getKey(),
            'min_quantity' => 1,
        ];

        $priceFirst = Price::query()->createOrFirst(
            $priceAttributes,
            ['amount' => 1200, 'currency' => 'MYR'],
        );

        $priceSecond = Price::query()->createOrFirst(
            $priceAttributes,
            ['amount' => 1200, 'currency' => 'MYR'],
        );

        return [
            $first->getKey(),
            $second->getKey(),
            $priceFirst->getKey(),
            $priceSecond->getKey(),
            PriceList::query()->where('slug', 'ensure-idempotent')->count(),
        ];
    });

    expect($ids[0])->toBe($ids[1]);
    expect($ids[2])->toBe($ids[3]);
    expect($ids[4])->toBe(1);
});
