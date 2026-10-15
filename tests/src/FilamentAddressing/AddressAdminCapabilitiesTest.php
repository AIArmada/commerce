<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\BuildAddressNavigationLinksAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\UpdateAddressAttachmentAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Models\State;
use AIArmada\Commerce\Tests\Fixtures\FilamentAddressingPanelUser;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentAddressing\RelationManagers\AddressesRelationManager;
use AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages\EditAddressCity;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\CreateAddress;
use AIArmada\FilamentAddressing\Resources\AddressResource\Pages\ViewAddress;
use AIArmada\FilamentAddressing\Resources\AddressSnapshotResource\Pages\ViewAddressSnapshot;
use AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages\EditAddressState;
use AIArmada\FilamentAddressing\Resources\ResolutionGapResource\Pages\ListResolutionGaps;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\EditCustomer;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

final class AddressAdminCapabilitiesTestPolicy
{
    /** @var array<string> */
    public static array $viewedAddressIds = [];

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Address $address): bool
    {
        self::$viewedAddressIds[] = (string) $address->getKey();

        return true;
    }
}

beforeEach(function (): void {
    AddressAdminCapabilitiesTestPolicy::$viewedAddressIds = [];
    app(SeedAddressCountriesAction::class)->execute();
    config()->set('auth.providers.users.model', FilamentAddressingPanelUser::class);
    Filament::auth()->login(FilamentAddressingPanelUser::query()->create(User::factory()->raw()));
    Gate::define('customers.addresses.create', fn (): bool => true);
    Gate::define('customers.addresses.update', fn (): bool => true);
    Gate::define('customers.addresses.view', fn (): bool => true);
    Gate::define('customers.customers.update', fn (): bool => true);
    Gate::policy(Address::class, AddressAdminCapabilitiesTestPolicy::class);
});

it('supports mounted address attachment creation and pivot editing', function (): void {
    $customer = Customer::factory()->create();
    $address = Address::query()->create(['line1' => '1 Admin Road', 'country_code' => 'MY']);

    $customer->attachAddress($address, type: 'shipping', label: 'Warehouse', isPrimary: true);
    $customer->attachAddress($address, type: 'billing', label: 'Accounts', isPrimary: false);
    $shipping = $customer->addresses()->wherePivot('type', 'shipping')->firstOrFail();
    $table = Livewire::test(AddressesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ]);
    $attachments = $table->instance()->getTableRecords()->getCollection();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('type')->all())->toEqualCanonicalizing(['shipping', 'billing'])
        ->and($attachments->pluck('label')->all())->toEqualCanonicalizing(['Warehouse', 'Accounts'])
        ->and($attachments->pluck('address_id')->unique()->all())->toBe([$address->getKey()]);
    $table->assertTableActionVisible('editAttachment', (string) $shipping->pivot->getKey());
    expect(AddressAdminCapabilitiesTestPolicy::$viewedAddressIds)->toContain((string) $address->getKey());

    Livewire::test(AddressesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])->callTableAction('editAttachment', record: $shipping->pivot->getKey(), data: [
        'type' => 'delivery',
        'label' => 'Accounts office',
        'is_primary' => true,
    ])->assertHasNoActionErrors();

    $pivot = Addressable::query()->whereKey($shipping->pivot->getKey())->firstOrFail();
    $otherPivot = Addressable::query()->where('address_id', $address->getKey())->where('type', 'billing')->firstOrFail();
    expect($pivot->type)->toBe('delivery')
        ->and($pivot->label)->toBe('Accounts office')
        ->and($pivot->is_primary)->toBeTrue()
        ->and($otherPivot->label)->toBe('Accounts');
});

it('attaches an existing address through the mounted owner-scoped action', function (): void {
    $customer = Customer::factory()->create();
    $address = Address::query()->create(['line1' => 'Attach Road', 'country_code' => 'MY']);

    Livewire::test(AddressesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])->callAction(TestAction::make('attachAddress')->table(), data: [
        'address_id' => (string) $address->getKey(),
        'type' => 'shipping',
        'label' => 'Receiving dock',
        'is_primary' => true,
    ])->assertHasNoActionErrors();

    $pivot = $customer->addresses()->wherePivot('type', 'shipping')->firstOrFail()->pivot;

    expect($pivot->address_id)->toBe($address->getKey())
        ->and($pivot->label)->toBe('Receiving dock')
        ->and($pivot->is_primary)->toBeTrue();
});

it('keeps geography identity fields out of mounted edit submissions', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'name' => 'Selangor',
        'code' => 'SGR',
    ]);
    $city = City::query()->create([
        'country_id' => $country->getKey(),
        'state_id' => $state->getKey(),
        'name' => 'Shah Alam',
    ]);
    $otherCountry = AddressCountry::query()->where('iso2', 'SG')->firstOrFail();

    Livewire::test(EditAddressState::class, ['record' => $state->getRouteKey()])
        ->fillForm(['name' => 'Selangor Admin', 'code' => 'FORGED', 'country_id' => $otherCountry->getKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($state->fresh()->name)->toBe('Selangor Admin')
        ->and($state->fresh()->code)->toBe('SGR')
        ->and($state->fresh()->country_id)->toBe($country->getKey());

    Livewire::test(EditAddressCity::class, ['record' => $city->getRouteKey()])
        ->fillForm(['name' => 'Forged City', 'country_id' => $otherCountry->getKey(), 'state_id' => null, 'latitude' => 3.1])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($city->fresh()->name)->toBe('Shah Alam')
        ->and($city->fresh()->country_id)->toBe($country->getKey())
        ->and($city->fresh()->state_id)->toBe($state->getKey())
        ->and($city->fresh()->latitude)->toBe(3.1);
});

it('keeps natural keys stable for states without provider codes', function (): void {
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $state = State::query()->create([
        'country_id' => $country->getKey(),
        'country_code' => 'MY',
        'name' => 'Manual state',
        'code' => null,
    ]);
    $page = app(EditAddressState::class);
    $page->record = $state;
    $mutate = new ReflectionMethod(EditAddressState::class, 'mutateFormDataBeforeSave');
    $mutate->setAccessible(true);

    expect($mutate->invoke($page, ['name' => 'Forged rename', 'latitude' => 3.12]))
        ->not->toHaveKey('name');

    Livewire::test(EditAddressState::class, ['record' => $state->getRouteKey()])
        ->fillForm(['name' => 'Forged rename', 'latitude' => 3.12])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($state->fresh()->name)->toBe('Manual state')
        ->and($state->fresh()->latitude)->toBe(3.12);
});

it('registers the address create route before the record route wildcard', function (): void {
    config()->set('filament-addressing.resources.addresses.read_only', false);

    expect(AddressResource::canViewAny())->toBeTrue()
        ->and(AddressResource::canCreate())->toBeTrue();

    $this->get(AddressResource::getUrl('create'))->assertOk();
});

it('validates postcode format against the selected country on the mounted address form', function (): void {
    Livewire::test(CreateAddress::class)
        ->fillForm(['country_code' => 'MY', 'postcode' => '123'])
        ->call('create')
        ->assertHasFormErrors(['postcode']);

    Livewire::test(CreateAddress::class)
        ->fillForm(['country_code' => 'MY', 'postcode' => '50450'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(Address::query()->where('postcode', '50450')->exists())->toBeTrue();
});

it('hides attachment mutations when the parent resource denies updates', function (): void {
    $customer = Customer::factory()->create();
    $address = Address::query()->create(['line1' => 'Denied Road', 'country_code' => 'MY']);
    $this->assertNotNull($customer->attachAddress($address, type: 'shipping'));
    $pivot = $customer->addresses()->wherePivot('type', 'shipping')->firstOrFail()->pivot;

    Gate::define('customers.customers.update', fn (): bool => false);

    Livewire::test(AddressesRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])->assertTableActionHidden('editAttachment', (string) $pivot->getKey())
        ->assertTableActionHidden('detachAddress', (string) $pivot->getKey());

    expect($pivot->fresh()->type)->toBe('shipping');
});

it('hides attachments whose related address is outside the current owner scope', function (): void {
    $owner = Filament::auth()->user();
    $otherOwner = User::factory()->create();
    $customer = OwnerContext::withOwner($owner, fn (): Customer => Customer::factory()->create());
    $foreignAddress = OwnerContext::withOwner($otherOwner, fn (): Address => Address::query()->create([
        'line1' => 'Foreign owner address',
        'country_code' => 'MY',
    ]));
    $forgedAttachment = OwnerContext::withOwner($owner, function () use ($customer, $foreignAddress, $owner): Addressable {
        $attachment = new Addressable;
        $attachment->forceFill([
            'address_id' => $foreignAddress->getKey(),
            'addressable_type' => $customer->getMorphClass(),
            'addressable_id' => $customer->getKey(),
            'type' => 'shipping',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        Addressable::withoutEvents(fn () => $attachment->save());

        return $attachment;
    });

    OwnerContext::withOwner($owner, function () use ($customer, $forgedAttachment): void {
        expect(Addressable::query()->whereKey($forgedAttachment->getKey())->exists())->toBeTrue();
        expect($customer->addressAttachments()->exists())->toBeFalse();
        expect(fn () => app(UpdateAddressAttachmentAction::class)->execute($forgedAttachment, [
            'type' => 'billing',
            'is_primary' => true,
        ]))->toThrow(ModelNotFoundException::class);
    });
});

it('generates safe navigation links from live and saved address coordinates', function (): void {
    $owner = Filament::auth()->user();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));
    $address = OwnerContext::withOwner($owner, fn (): Address => Address::query()->create([
        'line1' => 'Coordinate Address',
        'latitude' => 3.139,
        'longitude' => 101.686,
        'country_code' => 'MY',
        'google_maps_url' => 'javascript:alert(1)',
        'waze_url' => 'data:text/html,unsafe',
    ]));

    $links = app(BuildAddressNavigationLinksAction::class)->execute($address);

    expect($links['google_maps_url'])->toStartWith('https://www.google.com/maps/search/')
        ->and($links['waze_url'])->toStartWith('https://waze.com/ul?');

    $renderedAddress = OwnerContext::withOwner($owner, fn (): Address => Address::query()->create([
        'line1' => 'Generated Navigation Address',
        'latitude' => 3.139,
        'longitude' => 101.686,
        'country_code' => 'MY',
        'google_maps_url' => null,
        'waze_url' => null,
    ]));
    $renderedLinks = app(BuildAddressNavigationLinksAction::class)->execute($renderedAddress);

    OwnerContext::withOwner($owner, function () use ($renderedAddress, $renderedLinks): void {
        test()->get(AddressResource::getUrl('view', ['record' => $renderedAddress]))->assertOk();

        Livewire::test(ViewAddress::class, ['record' => $renderedAddress->getRouteKey()])
            ->assertSeeHtml('href="' . e($renderedLinks['google_maps_url']) . '"')
            ->assertSeeHtml('href="' . e($renderedLinks['waze_url']) . '"');
    });

    $snapshot = OwnerContext::withOwner($owner, fn (): AddressSnapshot => AddressSnapshot::query()->create([
        'snapshotable_type' => User::class,
        'snapshotable_id' => $owner->getKey(),
        'line1' => 'Saved Coordinate Address',
        'latitude' => 3.139,
        'longitude' => 101.686,
        'google_maps_url' => null,
        'waze_url' => null,
    ]));

    $snapshotLinks = app(BuildAddressNavigationLinksAction::class)->execute(
        AddressData::from($snapshot->attributesToArray()),
    );

    expect($snapshotLinks['google_maps_url'])->toStartWith('https://www.google.com/maps/search/');

    OwnerContext::withOwner($owner, function () use ($snapshot, $snapshotLinks): void {
        Livewire::test(ViewAddressSnapshot::class, ['record' => $snapshot->getRouteKey()])
            ->assertSeeHtml('href="' . e($snapshotLinks['google_maps_url']) . '"')
            ->assertSeeHtml('href="' . e($snapshotLinks['waze_url']) . '"');
    });

});

it('keeps tenant users from mutating globally included resolution gaps', function (): void {
    $owner = Customer::factory()->create();
    $gap = OwnerContext::withOwner(null, fn (): ResolutionGap => ResolutionGap::query()->create([
        'source' => 'admin-test',
        'country_code' => 'MY',
        'role' => 'city',
        'value' => 'Global Gap',
        'normalized' => 'global gap',
        'reason' => 'unmatched',
        'status' => 'open',
    ]));

    OwnerContext::withOwner($owner, function () use ($gap): void {
        Livewire::test(ListResolutionGaps::class)
            ->assertTableActionHidden('match', (string) $gap->getKey())
            ->assertTableActionHidden('ignore', (string) $gap->getKey());
    });

    config()->set('filament-addressing.resources.resolution_gaps.read_only', true);

    Livewire::test(ListResolutionGaps::class)
        ->assertTableActionHidden('ignore', (string) $gap->getKey());
});
