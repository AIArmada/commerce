<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentProducts\Resources\ProductResource\Schemas\ProductForm;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

class ProductFormCustomerSearchHost extends LivewireComponent implements HasSchemas
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

it('searches pricing customers by name without querying accessors', function (): void {
    $owner = User::query()->create([
        'name' => 'Product Form Owner',
        'email' => 'product-form-owner@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $customer = Customer::query()->create(['first_name' => 'Catalog', 'last_name' => 'Shopper']);
    Customer::query()->create(['first_name' => 'Unrelated', 'last_name' => 'Buyer']);

    $product = OwnerContext::withOwner($owner, static function (): Product {
        return Product::query()->create([
            'name' => 'Search Fixture Product',
            'slug' => 'search-fixture-product',
            'status' => ProductStatus::Active,
            'price' => 1000,
        ]);
    });

    $select = productFormPricingCustomerSelect($product);

    expect($select)->toBeInstanceOf(Select::class);

    $results = $select->getSearchResults('Shopper');

    expect($results)->toHaveKey((string) $customer->getKey())
        ->and($results)->toHaveCount(1);
});

function productFormPricingCustomerSelect(Product $product): ?Select
{
    $schema = Schema::make(new ProductFormCustomerSearchHost)->model(Product::class)->record($product);
    ProductForm::configure($schema);

    $found = $schema->getComponentByStatePath('pricing_customer_id');

    return $found instanceof Select ? $found : null;
}
