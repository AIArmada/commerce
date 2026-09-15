<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Actions\GenerateVariants;
use AIArmada\Products\Jobs\GenerateVariantsJob;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

function makeOwner(string $email): User
{
    return User::query()->create([
        'name' => 'Regeneration Owner',
        'email' => $email,
        'password' => 'secret',
    ]);
}

function seedMatrix(Product $product, string $optionName = 'Color', array $values = ['Red']): void
{
    $option = Option::create(['product_id' => $product->id, 'name' => $optionName]);

    foreach ($values as $position => $name) {
        OptionValue::create(['option_id' => $option->id, 'name' => $name, 'position' => $position + 1]);
    }
}

it('generates independent variants per owner when parent skus collide across owners', function (): void {
    $ownerA = makeOwner('regen-owner-a@example.com');
    $ownerB = makeOwner('regen-owner-b@example.com');

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create([
        'name' => 'Owner A Shirt', 'price' => 1000, 'sku' => 'SHARED-SKU',
    ]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create([
        'name' => 'Owner B Shirt', 'price' => 1000, 'sku' => 'SHARED-SKU',
    ]));

    OwnerContext::withOwner($ownerA, fn (): Product => tap($productA, fn () => seedMatrix($productA)));
    OwnerContext::withOwner($ownerB, fn (): Product => tap($productB, fn () => seedMatrix($productB)));

    $variantsA = OwnerContext::withOwner($ownerA, fn () => app(GenerateVariants::class)->execute($productA));
    $variantsB = OwnerContext::withOwner($ownerB, fn () => app(GenerateVariants::class)->execute($productB));

    expect($variantsA)->toHaveCount(1)
        ->and($variantsB)->toHaveCount(1)
        ->and($variantsA->first()->product_id)->toBe($productA->id)
        ->and($variantsB->first()->product_id)->toBe($productB->id)
        ->and($variantsA->first()->id)->not->toBe($variantsB->first()->id);
});

it('never attaches a sibling product variant during regeneration', function (): void {
    config()->set('products.features.variants.sku_pattern', '{option_codes}');

    $owner = makeOwner('regen-owner-c@example.com');

    $productA = OwnerContext::withOwner($owner, fn (): Product => Product::create([
        'name' => 'Sibling A', 'price' => 1000, 'sku' => 'SIBLING-A',
    ]));
    $productB = OwnerContext::withOwner($owner, fn (): Product => Product::create([
        'name' => 'Sibling B', 'price' => 1000, 'sku' => 'SIBLING-B',
    ]));

    OwnerContext::withOwner($owner, function () use ($productA, $productB): void {
        seedMatrix($productA);
        seedMatrix($productB);

        app(GenerateVariants::class)->execute($productA);

        // Both matrices yield the same sku; the second generation must fail
        // loudly instead of silently returning product A's variant.
        try {
            app(GenerateVariants::class)->execute($productB->fresh());
            $thrown = null;
        } catch (InvalidArgumentException | QueryException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->not->toBeNull()
            ->and(Variant::query()->where('product_id', $productB->id)->count())->toBe(0)
            ->and(Variant::query()->where('product_id', $productA->id)->count())->toBe(1);
    });
});

it('generates variants through the queued job for the payload owner', function (): void {
    $owner = makeOwner('regen-job-owner@example.com');

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::create([
        'name' => 'Job Product', 'price' => 1000,
    ]));
    OwnerContext::withOwner($owner, fn (): Product => tap($product, fn () => seedMatrix($product)));

    $job = new GenerateVariantsJob(
        productId: (string) $product->getKey(),
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
        ownerIsGlobal: false,
    );

    $job->handle();

    expect(Variant::query()->withoutOwnerScope()->where('product_id', $product->id)->count())->toBe(1);
});

it('ignores a job whose product belongs to a different owner', function (): void {
    $ownerA = makeOwner('regen-job-a@example.com');
    $ownerB = makeOwner('regen-job-b@example.com');

    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create([
        'name' => 'Other Owner Product', 'price' => 1000,
    ]));
    OwnerContext::withOwner($ownerB, fn (): Product => tap($productB, fn () => seedMatrix($productB)));

    // Tampered payload: owner A's context with owner B's product id.
    $job = new GenerateVariantsJob(
        productId: (string) $productB->getKey(),
        ownerType: $ownerA->getMorphClass(),
        ownerId: $ownerA->getKey(),
        ownerIsGlobal: false,
    );

    $job->handle();

    expect(Variant::query()->withoutOwnerScope()->where('product_id', $productB->id)->count())->toBe(0);
});

it('treats a missing job product as a no-op', function (): void {
    $owner = makeOwner('regen-job-missing@example.com');

    $job = new GenerateVariantsJob(
        productId: (string) Str::uuid(),
        ownerType: $owner->getMorphClass(),
        ownerId: $owner->getKey(),
        ownerIsGlobal: false,
    );

    expect(fn () => $job->handle())->not->toThrow(Exception::class);
});
