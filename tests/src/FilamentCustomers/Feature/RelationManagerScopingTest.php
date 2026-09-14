<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerNote;
use AIArmada\FilamentCustomers\Resources\CustomerResource;
use AIArmada\FilamentCustomers\Resources\CustomerResource\Pages\ViewCustomer;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use AIArmada\FilamentCustomers\Resources\CustomerResource\RelationManagers\NotesRelationManager;
use AIArmada\FilamentCustomers\Resources\SegmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as LivewireComponent;

if (! function_exists('filamentCustomers_makeOwner')) {
    function filamentCustomers_makeOwner(string $id): Model
    {
        return new class($id) extends Model
        {
            public $incrementing = false;

            protected $keyType = 'string';

            public function __construct(private readonly string $uuid) {}

            public function getKey(): mixed
            {
                return $this->uuid;
            }

            public function getMorphClass(): string
            {
                return 'tests:owner';
            }
        };
    }
}

if (! function_exists('filamentCustomers_makeSchemaLivewire')) {
    function filamentCustomers_makeSchemaLivewire(): LivewireComponent & HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas
        {
            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(string $key, bool $withHidden = false, array $skipComponentsChildContainersWhileSearching = []): Component | Action | ActionGroup | null
            {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void {}

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }
}

beforeEach(function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);
});

it('scopes the address attach picker to the current owner', function (): void {
    $ownerA = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');
    $ownerB = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000b');

    $customer = OwnerContext::withOwner($ownerA, fn (): Customer => Customer::query()->create([
        'first_name' => 'Attach',
        'last_name' => 'Customer',
        'status' => 'active',
        'accepts_marketing' => false,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]));

    $inScope = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Scoped Street',
        'city' => 'City',
        'postcode' => '11111',
        'country' => 'MY',
    ]));

    OwnerContext::withOwner($ownerB, fn (): Address => Address::query()->create([
        'line1' => 'Foreign Street',
        'city' => 'City',
        'postcode' => '22222',
        'country' => 'MY',
    ]));

    $manager = new AddressesRelationManager;
    $manager->ownerRecord = $customer;
    $manager->pageClass = ViewCustomer::class;

    $table = $manager->table(Table::make($manager));

    $attachAction = collect($table->getHeaderActions())
        ->first(fn (Action $action): bool => $action instanceof AttachAction);

    expect($attachAction)->toBeInstanceOf(AttachAction::class);

    $property = new ReflectionProperty(AttachAction::class, 'modifyRecordSelectOptionsQueryUsing');
    $property->setAccessible(true);

    $callback = $property->getValue($attachAction);

    expect($callback)->toBeInstanceOf(Closure::class);

    $ids = OwnerContext::withOwner($ownerA, fn (): array => $callback(Address::query())->pluck('id')->all());

    expect($ids)->toEqual([$inScope->id]);
});

it('validates country codes as two ascii letters', function (): void {
    $manager = new AddressesRelationManager(filamentCustomers_makeSchemaLivewire());
    $schema = $manager->form(Schema::make(filamentCustomers_makeSchemaLivewire()));

    $countryCode = $schema->getComponent('country_code');

    expect($countryCode->getMinLength())->toBe(2)
        ->and($countryCode->getMaxLength())->toBe(2)
        ->and($countryCode->getValidationRules())->toContain('alpha:ascii');
});

it('caps customer note length', function (): void {
    $manager = new NotesRelationManager(filamentCustomers_makeSchemaLivewire());
    $schema = $manager->form(Schema::make(filamentCustomers_makeSchemaLivewire()));

    $content = $schema->getComponent('content');

    expect($content->getMaxLength())->toBe(5000);
});

it('loads manual-assignment selects on search instead of preloading every row', function (): void {
    $segmentSchema = SegmentResource::form(Schema::make(filamentCustomers_makeSchemaLivewire()));
    $customersSelect = $segmentSchema->getComponent('customers');

    expect($customersSelect->isSearchable())->toBeTrue()
        ->and($customersSelect->isPreloaded())->toBeFalse();

    $customerSchema = CustomerResource::form(Schema::make(filamentCustomers_makeSchemaLivewire()));
    $segmentsSelect = $customerSchema->getComponent('segments');

    expect($segmentsSelect->isSearchable())->toBeTrue()
        ->and($segmentsSelect->isPreloaded())->toBeFalse();
});

it('eager-loads contact methods, segments, and note authors', function (): void {
    $eagerLoads = CustomerResource::getEloquentQuery()->getEagerLoads();

    expect($eagerLoads)->toHaveKey('contactMethods')
        ->and($eagerLoads)->toHaveKey('segments');

    $owner = filamentCustomers_makeOwner('00000000-0000-0000-0000-00000000000a');

    $customer = OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Notes',
        'last_name' => 'Customer',
        'status' => 'active',
        'accepts_marketing' => false,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]));

    $manager = new NotesRelationManager;
    $manager->ownerRecord = $customer;
    $manager->pageClass = ViewCustomer::class;

    $table = $manager->table(Table::make($manager));

    $scoped = $table->applyQueryScopes(CustomerNote::query());

    expect($scoped->getEagerLoads())->toHaveKey('createdBy');
});
