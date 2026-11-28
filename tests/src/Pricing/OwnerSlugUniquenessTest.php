<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Data\RecordReference;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Pricing\Actions\EnsurePriceListBySlug;
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

            public function resolve(): ?RecordReference
            {
                return EloquentRecordReference::fromModel($this->owner);
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

it('enforces unique global slugs while allowing a tenant to reuse them', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.database.tables.price_lists', 'global_slug_price_lists');

    Schema::dropIfExists('global_slug_price_lists');
    $migration = require __DIR__ . '/../../../packages/pricing/database/migrations/2000_12_01_000001_create_price_lists_table.php';
    $migration->up();

    try {
        $owner = createSlugPricingOwner('Global Slug Owner', 'global-slug-owner@example.com');

        $global = OwnerContext::withOwner(null, fn (): PriceList => PriceList::query()->create(['name' => 'Global Retail', 'slug' => 'retail']));
        $tenant = OwnerContext::withOwner($owner, fn (): PriceList => PriceList::query()->create(['name' => 'Tenant Retail', 'slug' => 'retail']));

        expect($global->getKey())->not->toBe($tenant->getKey());

        expect(fn () => OwnerContext::withOwner(null, fn (): PriceList => PriceList::query()->create([
            'name' => 'Duplicate Global Retail',
            'slug' => 'retail',
        ])))->toThrow(UniqueConstraintViolationException::class);
    } finally {
        Schema::dropIfExists('global_slug_price_lists');
    }
});

it('ensures lists within the exact owner even when global lists are visible', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', true);
    config()->set('pricing.database.tables.price_lists', 'ensure_scoped_price_lists');

    Schema::dropIfExists('ensure_scoped_price_lists');
    $migration = require __DIR__ . '/../../../packages/pricing/database/migrations/2000_12_01_000001_create_price_lists_table.php';
    $migration->up();

    try {
        $owner = createSlugPricingOwner('Scoped Ensure Owner', 'scoped-ensure-owner@example.com');
        $action = app(EnsurePriceListBySlug::class);
        $global = OwnerContext::withOwner(null, fn (): PriceList => $action->execute('retail', ['name' => 'Global Retail']));

        OwnerContext::withOwner($owner, function () use ($action, $global, $owner): void {
            $tenant = $action->execute('retail', ['name' => 'Tenant Retail']);
            $updated = $action->execute('retail', ['name' => 'Updated Retail', 'slug' => 'different']);

            expect($tenant->id)->not->toBe($global->id)
                ->and($tenant->owner_id)->toBe($owner->getKey())
                ->and($updated->id)->toBe($tenant->id)
                ->and($updated->slug)->toBe('retail')
                ->and($updated->name)->toBe('Updated Retail');
        });

        expect(OwnerContext::withOwner(null, fn (): string => $global->fresh()->name))->toBe('Global Retail');
    } finally {
        Schema::dropIfExists('ensure_scoped_price_lists');
    }
});
