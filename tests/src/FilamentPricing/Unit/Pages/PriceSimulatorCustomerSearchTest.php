<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentPricing\Pages\PriceSimulator;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

uses(TestCase::class);

it('searches simulator customers by name without querying accessors', function (): void {
    $owner = User::query()->create([
        'name' => 'Simulator Owner',
        'email' => 'simulator-owner@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $customer = Customer::query()->create(['first_name' => 'Sim', 'last_name' => 'Ulator']);
    Customer::query()->create(['first_name' => 'Unrelated', 'last_name' => 'Person']);

    $select = priceSimCustomerSelect();

    expect($select)->toBeInstanceOf(Select::class);

    $results = $select->getSearchResults('Ulator');

    expect($results)->toHaveKey((string) $customer->getKey())
        ->and($results)->toHaveCount(1);
});

it('matches simulator customers by company name', function (): void {
    $owner = User::query()->create([
        'name' => 'Simulator Company Owner',
        'email' => 'simulator-company-owner@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $customer = Customer::query()->create([
        'first_name' => 'Acme',
        'last_name' => 'Buyer',
        'company' => 'Acme Industrial Widgets',
    ]);

    $select = priceSimCustomerSelect();

    expect($select)->toBeInstanceOf(Select::class);

    $results = $select->getSearchResults('Industrial');

    expect($results)->toHaveKey((string) $customer->getKey());
});

function priceSimCustomerSelect(): ?Select
{
    $page = app(PriceSimulator::class);
    $schema = $page->form(Schema::make($page));

    $found = priceSimFindComponent($schema->getComponents(), 'customer_id');

    return $found instanceof Select ? $found : null;
}

function priceSimFindComponent(array $components, string $name): mixed
{
    foreach ($components as $component) {
        if (method_exists($component, 'getName') && $component->getName() === $name) {
            return $component;
        }

        if (method_exists($component, 'getChildComponents')) {
            $found = priceSimFindComponent($component->getChildComponents(), $name);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}
