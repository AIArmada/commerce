<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\Pricing\Models\PriceList;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
    config()->set('products.features.owner.auto_assign_on_create', true);
});

function resolveUniquenessOwner(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function ownerScopedSlugRule(): Unique
{
    return OwnerUniqueRule::scopeToOwner(Rule::unique('products', 'slug'), Product::class);
}

it('scopes slug uniqueness to the resolved owner', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    OwnerContext::withOwner($ownerA, static function (): void {
        Product::query()->create([
            'name' => 'Taken',
            'slug' => 'taken-slug',
            'price' => 1000,
        ]);
    });

    resolveUniquenessOwner($ownerA);

    $sameOwner = Validator::make(['slug' => 'taken-slug'], ['slug' => ownerScopedSlugRule()]);

    expect($sameOwner->fails())->toBeTrue();

    resolveUniquenessOwner($ownerB);

    $otherOwner = Validator::make(['slug' => 'taken-slug'], ['slug' => ownerScopedSlugRule()]);

    expect($otherOwner->fails())->toBeFalse();
});

it('matches global rows when global identities are included', function (): void {
    config()->set('products.features.owner.include_global', true);

    $owner = TestOwner::query()->create(['name' => 'Owner']);

    OwnerContext::withOwner(null, static function (): void {
        Product::query()->create([
            'owner_type' => null,
            'owner_id' => null,
            'name' => 'Global Taken',
            'slug' => 'global-taken-slug',
            'price' => 1000,
        ]);
    });

    resolveUniquenessOwner($owner);

    $validator = Validator::make(['slug' => 'global-taken-slug'], ['slug' => ownerScopedSlugRule()]);

    expect($validator->fails())->toBeTrue();
});

it('leaves uniqueness unscoped when owner mode is disabled', function (): void {
    config()->set('products.features.owner.enabled', false);

    $rule = OwnerUniqueRule::scopeToOwner(Rule::unique('products', 'slug'), Product::class);

    expect((string) $rule)->toBe((string) Rule::unique('products', 'slug'));
});

it('revalidates price list ownership for cross-owner submissions', function (): void {
    config()->set('pricing.features.owner.enabled', true);
    config()->set('pricing.features.owner.include_global', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);
    resolveUniquenessOwner($ownerA);

    $ownList = PriceList::query()->create([
        'name' => 'Own',
        'slug' => 'own-list',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $foreignList = OwnerContext::withOwner($ownerB, static fn (): PriceList => PriceList::query()->create([
        'name' => 'Foreign',
        'slug' => 'foreign-list',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]));

    expect(OwnerScopedIds::ensureAllowed('price_list_id', PriceList::class, [$ownList->id]))
        ->toBe([$ownList->id])
        ->and(fn () => OwnerScopedIds::ensureAllowed('price_list_id', PriceList::class, [$foreignList->id]))
        ->toThrow(ValidationException::class);
});
