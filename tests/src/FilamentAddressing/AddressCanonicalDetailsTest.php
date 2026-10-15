<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaAssignment;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\State;
use AIArmada\Commerce\Tests\Fixtures\FilamentAddressingPanelUser;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ViewAddress;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

final class AddressCanonicalDetailsTestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Address $address): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    config()->set('auth.providers.users.model', FilamentAddressingPanelUser::class);
    Gate::policy(Address::class, AddressCanonicalDetailsTestPolicy::class);
});

it('shows canonical location references and only the current owners area assignments', function (): void {
    $owner = FilamentAddressingPanelUser::query()->create(User::factory()->raw());
    Filament::auth()->login($owner);
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'name' => 'Canonical Selangor',
        'code' => '10',
    ]);
    $city = City::query()->create([
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'country_code' => 'MY',
        'state_code' => '10',
        'name' => 'Canonical Petaling Jaya',
    ]);
    $area = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Current Owner Area',
        'slug' => 'current-owner-area',
        'source' => 'test',
        'source_id' => 'current-owner-area',
    ]);
    $foreignArea = AddressArea::query()->create([
        'country_code' => 'MY',
        'type' => 'district',
        'level' => 2,
        'name' => 'Foreign Owner Area',
        'slug' => 'foreign-owner-area',
        'source' => 'test',
        'source_id' => 'foreign-owner-area',
    ]);

    $address = OwnerContext::withOwner($owner, fn (): Address => Address::query()->create([
        'line1' => 'Canonical Address',
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'state_id' => $state->getKey(),
        'city_id' => $city->getKey(),
    ]));
    OwnerContext::withOwner($owner, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $address->getKey(),
        'address_area_id' => $area->getKey(),
        'role' => 'billing',
        'is_primary' => true,
    ]));

    $foreignOwner = User::factory()->create();
    $foreignAddress = OwnerContext::withOwner($foreignOwner, fn (): Address => Address::query()->create([
        'line1' => 'Foreign Address',
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
    ]));
    OwnerContext::withOwner($foreignOwner, fn (): AddressAreaAssignment => AddressAreaAssignment::query()->create([
        'address_id' => $foreignAddress->getKey(),
        'address_area_id' => $foreignArea->getKey(),
        'role' => 'shipping',
        'is_primary' => true,
    ]));

    OwnerContext::withOwner($owner, function () use ($address, $area, $country, $state, $city, $foreignArea): void {
        Livewire::test(ViewAddress::class, ['record' => $address->getRouteKey()])
            ->assertSeeText('Canonical Location')
            ->assertSeeText($country->name)
            ->assertSeeText($country->getKey())
            ->assertSeeText('Canonical Selangor')
            ->assertSeeText($state->getKey())
            ->assertSeeText('Canonical Petaling Jaya')
            ->assertSeeText($city->getKey())
            ->assertSeeText('Area Assignments')
            ->assertSeeText('billing')
            ->assertSeeText($area->name)
            ->assertSeeText('district')
            ->assertSeeText('Primary')
            ->assertDontSeeText($foreignArea->name);
    });
});
