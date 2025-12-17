<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\ViewCustomer;
use Filament\Actions\Action;

it('ViewCustomer credit actions update wallet balance', function (): void {
    $user = User::create([
        'name' => 'Customers Admin',
        'email' => 'customers-admin@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user);

    $customer = Customer::query()->create([
        'first_name' => 'Alice',
        'last_name' => 'Customer',
        'email' => 'alice@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $page = new ViewCustomer;
    $page->mount($customer->getKey());

    $headerActions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);
        /** @var array<int, Action> $actions */
        $actions = $method->invoke($page);

        return $actions;
    })();

    /** @var Action $add */
    $add = collect($headerActions)->first(fn (Action $action): bool => $action->getName() === 'add_credit');
    $add->livewire($page);
    $add->call(['data' => ['amount' => 10, 'reason' => 'Test']]);

    expect(Customer::find($customer->id)?->wallet_balance)->toBe(1000);

    /** @var Action $deduct */
    $deduct = collect($headerActions)->first(fn (Action $action): bool => $action->getName() === 'deduct_credit');
    $deduct->livewire($page);
    $deduct->call(['data' => ['amount' => 5, 'reason' => 'Test']]);

    expect(Customer::find($customer->id)?->wallet_balance)->toBe(500);
});

it('ViewCustomer deduct credit prevents overdraft', function (): void {
    $user = User::create([
        'name' => 'Customers Admin 2',
        'email' => 'customers-admin-2@example.com',
        'password' => 'password',
    ]);

    $this->actingAs($user);

    $customer = Customer::query()->create([
        'first_name' => 'Bob',
        'last_name' => 'Customer',
        'email' => 'bob@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
        'is_tax_exempt' => false,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $page = new ViewCustomer;
    $page->mount($customer->getKey());

    $headerActions = (function () use ($page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);
        /** @var array<int, Action> $actions */
        $actions = $method->invoke($page);

        return $actions;
    })();

    /** @var Action $deduct */
    $deduct = collect($headerActions)->first(fn (Action $action): bool => $action->getName() === 'deduct_credit');
    $deduct->livewire($page);
    $deduct->call(['data' => ['amount' => 5, 'reason' => 'Test']]);

    expect(Customer::find($customer->id)?->wallet_balance)->toBe(0);
});
