<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Address;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\ViewCustomer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\NotesRelationManager;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('custom relation manager actions require authentication (abort 403)', function (): void {
    config()->set('customers.features.owner.enabled', true);

    $user = User::query()->create([
        'name' => 'RM Admin',
        'email' => 'rm-admin-' . uniqid() . '@example.com',
        'password' => 'password',
    ]);

    test()->actingAs($user);

    $customer = OwnerContext::withOwner(null, fn (): Customer => Customer::query()->create([
        'first_name' => 'RM',
        'last_name' => 'Customer',
        'email' => 'rm-customer-' . uniqid() . '@example.com',
        'status' => 'active',
        'accepts_marketing' => false,
    ]));

    $address = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'customer_id' => $customer->getKey(),
        'type' => 'both',
        'line1' => 'Line 1',
        'city' => 'City',
        'postcode' => '12345',
        'country' => 'MY',
        'is_default_billing' => false,
        'is_default_shipping' => false,
    ]));

    $note = OwnerContext::withOwner(null, fn (): CustomerNote => CustomerNote::query()->create([
        'customer_id' => $customer->getKey(),
        'content' => 'Pinned note',
        'is_internal' => true,
        'is_pinned' => false,
    ]));

    $addressesRm = new AddressesRelationManager;
    $addressesRm->ownerRecord = $customer;
    $addressesRm->pageClass = ViewCustomer::class;

    $addressesTable = $addressesRm->table(Table::make($addressesRm));

    /** @var Action $setBilling */
    $setBilling = $addressesTable->getAction('set_billing');
    $setBilling->livewire($addressesRm);
    $setBilling->record($address);

    $notesRm = new NotesRelationManager;
    $notesRm->ownerRecord = $customer;
    $notesRm->pageClass = ViewCustomer::class;

    $notesTable = $notesRm->table(Table::make($notesRm));

    /** @var Action $pin */
    $pin = $notesTable->getAction('pin');
    $pin->livewire($notesRm);
    $pin->record($note);

    Auth::logout();

    expect(fn (): mixed => $setBilling->call())->toThrow(HttpException::class);
    expect(fn (): mixed => $pin->call())->toThrow(HttpException::class);
});
