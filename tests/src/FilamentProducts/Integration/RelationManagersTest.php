<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers\OptionsRelationManager;
use AIArmada\FilamentProducts\Resources\ProductResource\RelationManagers\VariantsRelationManager;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\Product;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

it('executes option manage_values action to create/update/delete values', function (): void {
    $product = Product::query()->create([
        'name' => 'P',
        'slug' => 'p',
        'currency' => 'MYR',
        'price' => 1000,
        'status' => ProductStatus::Active,
    ]);

    /** @var Option $option */
    $option = $product->options()->create([
        'name' => 'Color',
        'display_name' => 'Color',
        'position' => 0,
        'is_visible' => true,
    ]);

    $existing = $option->values()->create([
        'name' => 'Red',
        'position' => 0,
    ]);

    $manager = app(OptionsRelationManager::class);

    expect($manager->form(Schema::make()))->toBeInstanceOf(Schema::class);

    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = $manager->table(Table::make($livewire));

    $action = $table->getAction('manage_values');
    $handler = $action?->record($option)->getActionFunction();

    expect($handler)->not->toBeNull();

    $handler($option, [
        'option_values' => [
            ['id' => $existing->id, 'name' => 'Red Updated', 'position' => 1],
            ['name' => 'Blue', 'position' => 2],
        ],
    ]);

    $option->refresh();

    expect($option->values()->count())->toBe(2);
    expect($option->values()->pluck('name')->all())
        ->toContain('Red Updated', 'Blue');
});

it('exercises variant relation manager actions and bulk actions', function (): void {
    $product = Product::query()->create([
        'name' => 'P',
        'slug' => 'p2',
        'currency' => 'MYR',
        'price' => 1000,
        'status' => ProductStatus::Active,
    ]);

    $variant = $product->variants()->create([
        'sku' => 'SKU-1',
        'price' => 1000,
        'is_enabled' => false,
        'is_default' => false,
    ]);

    $manager = app(VariantsRelationManager::class);
    invade($manager)->ownerRecord = $product;

    expect($manager->form(Schema::make()))->toBeInstanceOf(Schema::class);

    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);
    $table = $manager->table(Table::make($livewire));

    /** @var \Filament\Actions\EditAction $edit */
    $edit = $table->getAction('edit');

    expect($edit)->not->toBeNull();

    $recordMutator = (function () use ($edit) {
        $property = new ReflectionProperty($edit::class, 'mutateRecordDataUsing');
        $property->setAccessible(true);

        return $property->getValue($edit);
    })();

    expect($recordMutator)->not->toBeNull();

    expect($recordMutator(['price' => 1000]))->toBe(['price' => 10]);

    $edit->mutateFormDataUsing(fn (array $data): array => $data);
    $edit->data(['price' => 10.0]);

    // Re-run the configured mutator from the relation manager.
    $edit = $manager->table(Table::make($livewire))->getAction('edit');
    $edit->data(['price' => 10.0]);

    expect($edit->getData()['price'])->toBe(1000);
});
