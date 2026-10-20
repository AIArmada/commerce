<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

uses(TestCase::class);

beforeEach(function (): void {
    SchemaFacade::dropIfExists('test_owners');

    SchemaFacade::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('products.features.owner.enabled', true);
    config()->set('products.features.owner.include_global', false);
});

it('rejects forged cross-owner ids before every catalog relationship sync', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Boundary Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Boundary Owner B']);

    [$productB, $categoryB, $attributeB, $attributeGroupB] = OwnerContext::withOwner($ownerB, fn (): array => [
        Product::create(['name' => 'Owner B Product', 'price' => 1000]),
        Category::create(['name' => 'Owner B Category', 'slug' => 'owner-b-category']),
        Attribute::create(['code' => 'owner_b_attribute', 'name' => 'Owner B Attribute', 'type' => AttributeType::Text]),
        AttributeGroup::create(['code' => 'owner_b_group', 'name' => 'Owner B Group']),
    ]);

    [$productA, $collectionA, $attributeA, $attributeGroupA, $attributeSetA] = OwnerContext::withOwner($ownerA, fn (): array => [
        Product::create(['name' => 'Owner A Product', 'price' => 1000]),
        Collection::create(['name' => 'Owner A Collection', 'slug' => 'owner-a-collection']),
        Attribute::create(['code' => 'owner_a_attribute', 'name' => 'Owner A Attribute', 'type' => AttributeType::Text]),
        AttributeGroup::create(['code' => 'owner_a_group', 'name' => 'Owner A Group']),
        AttributeSet::create(['code' => 'owner_a_set', 'name' => 'Owner A Set']),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    /** @var array<int, array{0: class-string, 1: Model, 2: string, 3: string}> $cases */
    $cases = [
        [ProductResource::class, $productA, 'categories', $categoryB->id],
        [CollectionResource::class, $collectionA, 'products', $productB->id],
        [AttributeResource::class, $attributeA, 'groups', $attributeGroupB->id],
        [AttributeGroupResource::class, $attributeGroupA, 'groupAttributes', $attributeB->id],
        [AttributeSetResource::class, $attributeSetA, 'setAttributes', $attributeB->id],
        [AttributeSetResource::class, $attributeSetA, 'groups', $attributeGroupB->id],
    ];

    foreach ($cases as [$resource, $record, $field, $forgedId]) {
        $livewire = new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;
        };

        $schema = Schema::make($livewire)->record($record);
        $resource::form($schema);

        $component = $schema->getComponentByStatePath($field);

        expect($component)->toBeInstanceOf(Select::class);

        /** @var Select $component */
        $component->state([$forgedId]);

        try {
            $component->saveRelationshipsBeforeChildren();

            $this->fail("Field [{$field}] of [{$resource}] accepted a forged id.");
        } catch (ValidationException $exception) {
            expect(array_keys($exception->errors()))->toBe([$field]);
        }
    }
});

it('still accepts same-owner ids at the relationship write boundary', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Allowed Owner A']);

    [$productA, $categoryA] = OwnerContext::withOwner($ownerA, fn (): array => [
        Product::create(['name' => 'Allowed Product', 'price' => 1000]),
        Category::create(['name' => 'Allowed Category', 'slug' => 'allowed-category']),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = Schema::make($livewire)->record($productA);
    ProductResource::form($schema);

    /** @var Select $component */
    $component = $schema->getComponentByStatePath('categories');

    $component->state([$categoryA->id]);
    $component->saveRelationshipsBeforeChildren();
    $component->saveRelationships();

    expect($productA->categories()->pluck('product_categories.id')->all())->toBe([$categoryA->id]);
});
