<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

uses(TestCase::class);

it('limits the product categories select query to the required columns', function (): void {
    Category::query()->create([
        'name' => 'AI Strategy',
        'slug' => 'ai-strategy',
        'metadata' => ['source' => 'test'],
    ]);

    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = Schema::make($livewire)->model(Product::class);
    ProductResource::form($schema);

    $component = $schema->getComponentByStatePath('categories');

    expect($component)->toBeInstanceOf(Select::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    /** @var Select $component */
    $options = $component->getOptionsForJs();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_contains($query, 'product_categories'))
        ->implode("\n");

    expect($options)->not->toBeEmpty()
        ->and($queries)->toContain('"product_categories"."id"')
        ->and($queries)->toContain('"product_categories"."name"')
        ->and($queries)->not->toContain('"product_categories".*');
});

// scopeCategoriesQuery was removed along with BulkEditProducts page
