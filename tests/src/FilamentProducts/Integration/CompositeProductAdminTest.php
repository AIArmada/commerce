<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\CreateProduct;
use AIArmada\FilamentProducts\Resources\ProductResource\Pages\EditProduct;
use AIArmada\FilamentProducts\Resources\ProductResource\Schemas\ProductForm;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

class CompositeProductFormHost extends LivewireComponent implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render(): string
    {
        return '';
    }
}

function makeCompositeAdminComponent(string $sku, int $price = 1000): Product
{
    return Product::query()->create([
        'name' => 'Component ' . $sku,
        'slug' => Str::slug('component-' . $sku),
        'sku' => $sku,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => $price,
        'currency' => 'MYR',
        'requires_shipping' => true,
        'tracks_inventory' => true,
        'is_taxable' => true,
    ]);
}

it('renders composite component fields and the price mode field', function (): void {
    $package = Product::query()->create([
        'name' => 'Form Package',
        'slug' => 'form-package',
        'sku' => 'FORM-PKG',
        'type' => ProductType::Composite,
        'status' => ProductStatus::Draft,
        'price' => 5000,
        'price_mode' => 'sum',
    ]);

    $schema = Schema::make(new CompositeProductFormHost)->model(Product::class)->record($package);
    ProductForm::configure($schema);

    $components = $schema->getComponentByStatePath('components', withHidden: true);
    $priceMode = $schema->getComponentByStatePath('price_mode', withHidden: true);

    expect($components)->toBeInstanceOf(Repeater::class)
        ->and($priceMode)->toBeInstanceOf(Select::class);
});

it('creates a composite package with components in one action transaction', function (): void {
    $headset = makeCompositeAdminComponent('ADMIN-HEADSET', 24900);
    $mouse = makeCompositeAdminComponent('ADMIN-MOUSE', 19900);

    $page = new CreateProduct;

    $method = new ReflectionMethod(CreateProduct::class, 'handleRecordCreation');

    $package = $method->invoke($page, [
        'name' => 'Admin Composite',
        'slug' => 'admin-composite',
        'sku' => 'ADMIN-PKG',
        'status' => ProductStatus::Active->value,
        'type' => ProductType::Composite->value,
        'price' => 39900,
        'price_mode' => 'fixed',
        'currency' => 'MYR',
        'is_taxable' => true,
        'components' => [
            [
                'component_product_id' => $headset->id,
                'component_variant_id' => null,
                'quantity' => 1,
                'position' => 0,
            ],
            [
                'component_product_id' => $mouse->id,
                'component_variant_id' => null,
                'quantity' => 2,
                'position' => 1,
            ],
        ],
    ]);

    expect($package)->toBeInstanceOf(Product::class)
        ->and($package->type)->toBe(ProductType::Composite)
        ->and($package->status)->toBe(ProductStatus::Active)
        ->and($package->price_mode)->toBe(CompositePriceMode::Fixed)
        ->and(ProductComponent::query()->where('product_id', $package->id)->count())->toBe(2);

    $component = ProductComponent::query()
        ->where('product_id', $package->id)
        ->where('component_product_id', $mouse->id)
        ->first();

    expect($component)->not->toBeNull()
        ->and($component?->quantity)->toBe(2);
});

it('updates composite components and publishes the package through domain actions', function (): void {
    $headset = makeCompositeAdminComponent('EDIT-HEADSET', 24900);
    $mouse = makeCompositeAdminComponent('EDIT-MOUSE', 19900);

    $package = app(CreateCompositeProduct::class)->handle([
        'name' => 'Draft Composite',
        'slug' => 'draft-composite',
        'sku' => 'DRAFT-PKG',
        'status' => ProductStatus::Draft,
        'price' => 39900,
        'price_mode' => 'fixed',
        'currency' => 'MYR',
        'is_taxable' => true,
    ], [
        ['component_product_id' => $headset->id, 'quantity' => 1, 'position' => 0],
    ]);

    $page = new EditProduct;

    $method = new ReflectionMethod(EditProduct::class, 'handleRecordUpdate');

    $updated = $method->invoke($page, $package, [
        'name' => 'Published Composite',
        'status' => ProductStatus::Active->value,
        'price_mode' => 'sum',
        'components' => [
            [
                'component_product_id' => $headset->id,
                'component_variant_id' => null,
                'quantity' => 2,
                'position' => 0,
            ],
            [
                'component_product_id' => $mouse->id,
                'component_variant_id' => null,
                'quantity' => 1,
                'position' => 1,
            ],
        ],
    ]);

    expect($updated)->toBeInstanceOf(Product::class)
        ->and($updated->name)->toBe('Published Composite')
        ->and($updated->status)->toBe(ProductStatus::Active)
        ->and($updated->price_mode)->toBe(CompositePriceMode::Sum)
        ->and(ProductComponent::query()->where('product_id', $package->id)->count())->toBe(2);

    $headsetComponent = ProductComponent::query()
        ->where('product_id', $package->id)
        ->where('component_product_id', $headset->id)
        ->first();

    expect($headsetComponent?->quantity)->toBe(2);
});
