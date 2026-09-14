<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentProducts\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentProducts\Resources\CategoryResource\Pages\EditCategory;
use AIArmada\FilamentProducts\Resources\CategoryResource\Tables\CategoriesTable;
use AIArmada\Products\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

function invokeCategorySaveMutator(EditCategory $page, array $data): array
{
    $method = new ReflectionMethod(EditCategory::class, 'mutateFormDataBeforeSave');

    return $method->invoke($page, $data);
}

function resolveCategoryOwner(?Model $owner): void
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

it('rejects a category parented to itself', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);
    resolveCategoryOwner($owner);

    $category = OwnerContext::withOwner($owner, static fn (): Category => Category::query()->create([
        'name' => 'Selfish',
        'slug' => 'selfish',
    ]));

    $page = new EditCategory;
    $page->record = $category;

    expect(fn () => invokeCategorySaveMutator($page, ['parent_id' => $category->id]))
        ->toThrow(ValidationException::class, 'own parent');
});

it('rejects a category parented to one of its descendants', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);
    resolveCategoryOwner($owner);

    [$root, $child, $grandchild] = OwnerContext::withOwner($owner, static function (): array {
        $root = Category::query()->create(['name' => 'Root', 'slug' => 'cycle-root']);
        $child = Category::query()->create(['name' => 'Child', 'slug' => 'cycle-child', 'parent_id' => $root->id]);
        $grandchild = Category::query()->create(['name' => 'Grandchild', 'slug' => 'cycle-grandchild', 'parent_id' => $child->id]);

        return [$root, $child, $grandchild];
    });

    $page = new EditCategory;
    $page->record = $root;

    expect(fn () => invokeCategorySaveMutator($page, ['parent_id' => $grandchild->id]))
        ->toThrow(ValidationException::class, 'cycle');
});

it('allows moving a category to an unrelated parent', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner']);
    resolveCategoryOwner($owner);

    [$category, $newParent] = OwnerContext::withOwner($owner, static function (): array {
        return [
            Category::query()->create(['name' => 'Mover', 'slug' => 'mover']),
            Category::query()->create(['name' => 'New Parent', 'slug' => 'new-parent']),
        ];
    });

    $page = new EditCategory;
    $page->record = $category;

    $data = invokeCategorySaveMutator($page, ['parent_id' => $newParent->id]);

    expect($data['parent_id'])->toBe($newParent->id);
});

it('computes category depth without hanging on cyclic data', function (): void {
    $depth = new ReflectionMethod(CategoriesTable::class, 'depthFromMap');

    $acyclic = $depth->invoke(null, 'c', ['a' => null, 'b' => 'a', 'c' => 'b']);
    $cyclic = $depth->invoke(null, 'a', ['a' => 'b', 'b' => 'a']);
    $selfParent = $depth->invoke(null, 'a', ['a' => 'a']);

    expect($acyclic)->toBe(2)
        ->and($cyclic)->toBe(1)
        ->and($selfParent)->toBe(0);
});

it('loads the category parent map scoped to the resolved owner', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);
    resolveCategoryOwner($ownerA);

    $visible = OwnerContext::withOwner($ownerA, static fn (): Category => Category::query()->create([
        'name' => 'Visible',
        'slug' => 'map-visible',
    ]));

    OwnerContext::withOwner($ownerB, static fn (): Category => Category::query()->create([
        'name' => 'Hidden',
        'slug' => 'map-hidden',
    ]));

    $load = new ReflectionMethod(CategoriesTable::class, 'loadParentMap');
    $map = $load->invoke(null);

    expect($map)->toHaveKey($visible->id)
        ->and($map)->toHaveCount(1);
});
