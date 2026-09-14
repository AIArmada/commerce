<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\CustomerGroup;
use AIArmada\FilamentTax\Pages\ManageTaxSettings;
use AIArmada\FilamentTax\Policies\TaxClassPolicy;
use AIArmada\FilamentTax\Policies\TaxExemptionPolicy;
use AIArmada\FilamentTax\Policies\TaxRatePolicy;
use AIArmada\FilamentTax\Policies\TaxZonePolicy;
use AIArmada\FilamentTax\Resources\TaxClassResource;
use AIArmada\FilamentTax\Resources\TaxClassResource\Tables\TaxClassesTable;
use AIArmada\FilamentTax\Resources\TaxExemptionResource;
use AIArmada\FilamentTax\Resources\TaxExemptionResource\Tables\TaxExemptionsTable;
use AIArmada\FilamentTax\Resources\TaxRateResource;
use AIArmada\FilamentTax\Resources\TaxRateResource\Tables\TaxRatesTable;
use AIArmada\FilamentTax\Resources\TaxZoneResource;
use AIArmada\FilamentTax\Resources\TaxZoneResource\RelationManagers\RatesRelationManager\Tables\RatesTable;
use AIArmada\FilamentTax\Resources\TaxZoneResource\Tables\TaxZonesTable;
use AIArmada\FilamentTax\Widgets\ExpiringExemptionsWidget;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

uses(TestCase::class);

beforeEach(function (): void {
    Gate::policy(TaxZone::class, TaxZonePolicy::class);
    Gate::policy(TaxRate::class, TaxRatePolicy::class);
    Gate::policy(TaxClass::class, TaxClassPolicy::class);
    Gate::policy(TaxExemption::class, TaxExemptionPolicy::class);
});

class RoundTwoTaxTableHost extends LivewireComponent implements HasTable
{
    use InteractsWithTable;

    public function getTable(): Table
    {
        return Table::make($this);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function render(): string
    {
        return '';
    }
}

class RoundTwoTaxSchemaHost extends LivewireComponent implements HasSchemas
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

function roundTwoBindOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

function roundTwoUser(string $email): User
{
    return User::query()->create([
        'name' => 'Round Two',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);
}

function roundTwoGrant(string ...$abilities): void
{
    foreach ($abilities as $ability) {
        Gate::define($ability, fn (): bool => true);
    }
}

/**
 * @param  array<int, Action|ActionGroup>  $actions
 * @return array<int, Action>
 */
function roundTwoFlattenActions(array $actions): array
{
    $flat = [];

    foreach ($actions as $action) {
        if ($action instanceof ActionGroup) {
            $flat = [...$flat, ...roundTwoFlattenActions($action->getActions())];

            continue;
        }

        $flat[] = $action;
    }

    return $flat;
}

function roundTwoTableActions(Table $table): array
{
    return roundTwoFlattenActions([
        ...$table->getHeaderActions(),
        ...$table->getRecordActions(),
        ...$table->getToolbarActions(),
    ]);
}

function roundTwoFindAction(Table $table, string $name): Action
{
    $action = collect(roundTwoTableActions($table))
        ->firstWhere(fn (Action $candidate): bool => $candidate->getName() === $name);

    expect($action)->toBeInstanceOf(Action::class);

    return $action;
}

function roundTwoFindComponent(array $components, string $name): ?Component
{
    foreach ($components as $component) {
        if (! $component instanceof Component) {
            continue;
        }

        if (method_exists($component, 'getName') && $component->getName() === $name) {
            return $component;
        }

        if (method_exists($component, 'getChildComponents')) {
            $found = roundTwoFindComponent($component->getChildComponents(), $name);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function roundTwoExemptionForm(): array
{
    $host = new RoundTwoTaxSchemaHost;

    $schema = TaxExemptionResource::form(Schema::make($host))
        ->statePath('data')
        ->model(TaxExemption::class);

    return [$host, $schema];
}

function roundTwoSettingsData(array $overrides = []): array
{
    return [
        'enabled' => true,
        'defaultTaxRate' => 6.0,
        'defaultTaxName' => 'SST',
        'pricesIncludeTax' => false,
        'taxBasedOnShippingAddress' => true,
        'digitalGoodsTaxable' => true,
        'shippingTaxable' => false,
        'taxIdLabel' => 'SST Number',
        'validateTaxIds' => false,
        'requireExemptionCertificate' => false,
        ...$overrides,
    ];
}

it('denies tax zone listing without the view permission', function (): void {
    config()->set('tax.features.owner.enabled', false);

    test()->actingAs(roundTwoUser('r2-tax-denied@example.com'));

    expect(Gate::allows('viewAny', TaxZone::class))->toBeFalse()
        ->and(TaxZoneResource::canViewAny())->toBeFalse()
        ->and(TaxZoneResource::canCreate())->toBeFalse();
});

it('allows tax zone listing with the view permission', function (): void {
    config()->set('tax.features.owner.enabled', false);
    roundTwoGrant('tax.zones.view', 'tax.zones.create');

    test()->actingAs(roundTwoUser('r2-tax-allowed@example.com'));

    expect(Gate::allows('viewAny', TaxZone::class))->toBeTrue()
        ->and(TaxZoneResource::canViewAny())->toBeTrue()
        ->and(TaxZoneResource::canCreate())->toBeTrue();
});

it('denies cross-owner zone updates through the policy', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    roundTwoGrant('tax.zones.update');

    test()->actingAs(roundTwoUser('r2-tax-owner-user@example.com'));

    $ownerA = roundTwoUser('r2-tax-owner-a@example.com');
    $ownerB = roundTwoUser('r2-tax-owner-b@example.com');

    roundTwoBindOwner($ownerA);
    $zoneA = TaxZone::query()->create(['name' => 'Zone A', 'code' => 'R2A', 'is_active' => true]);

    roundTwoBindOwner($ownerB);
    $zoneB = TaxZone::query()->create(['name' => 'Zone B', 'code' => 'R2B', 'is_active' => true]);

    roundTwoBindOwner($ownerA);

    expect(Gate::allows('update', $zoneA))->toBeTrue()
        ->and(Gate::allows('update', $zoneB))->toBeFalse()
        ->and(TaxZoneResource::canEdit($zoneA))->toBeTrue()
        ->and(TaxZoneResource::canEdit($zoneB))->toBeFalse();
});

it('hides every tax table action without permission', function (): void {
    config()->set('tax.features.owner.enabled', false);

    test()->actingAs(roundTwoUser('r2-tax-actions@example.com'));

    $buildTables = function (): array {
        $host = new RoundTwoTaxTableHost;

        return [
            TaxZonesTable::configure(Table::make($host)),
            TaxRatesTable::configure(Table::make($host)),
            TaxClassesTable::configure(Table::make($host)),
            TaxExemptionsTable::configure(Table::make($host)),
            RatesTable::configure(Table::make($host)),
        ];
    };

    $actions = collect($buildTables())->flatMap(fn (Table $table): array => roundTwoTableActions($table));

    expect($actions)->not->toBeEmpty();

    foreach ($actions as $action) {
        expect($action->isAuthorized())->toBeFalse("Action [{$action->getName()}] should be unauthorized without permission.");
    }

    roundTwoGrant(
        'tax.zones.view',
        'tax.zones.create',
        'tax.zones.update',
        'tax.zones.delete',
        'tax.rates.view',
        'tax.rates.create',
        'tax.rates.update',
        'tax.rates.delete',
        'tax.classes.create',
        'tax.classes.update',
        'tax.classes.delete',
        'tax.exemptions.view',
        'tax.exemptions.create',
        'tax.exemptions.update',
        'tax.exemptions.delete',
        'tax.exemptions.download',
        'tax.exemptions.approve',
        'tax.exemptions.renew',
        'tax.exemptions.reject',
    );

    // Actions memoize authorization per instance, so rebuild after granting.
    $granted = collect($buildTables())->flatMap(fn (Table $table): array => roundTwoTableActions($table));

    expect($granted)->toHaveCount($actions->count());

    foreach ($granted as $action) {
        expect($action->isAuthorized())->toBeTrue("Action [{$action->getName()}] should be authorized with permission.");
    }
});

it('gates every tax resource through policies', function (): void {
    config()->set('tax.features.owner.enabled', false);

    test()->actingAs(roundTwoUser('r2-tax-resources@example.com'));

    expect(TaxRateResource::canViewAny())->toBeFalse()
        ->and(TaxClassResource::canViewAny())->toBeFalse()
        ->and(TaxExemptionResource::canViewAny())->toBeFalse()
        ->and(TaxExemptionResource::canCreate())->toBeFalse();

    roundTwoGrant(
        'tax.rates.view',
        'tax.classes.view',
        'tax.exemptions.view',
        'tax.exemptions.create',
    );

    expect(TaxRateResource::canViewAny())->toBeTrue()
        ->and(TaxClassResource::canViewAny())->toBeTrue()
        ->and(TaxExemptionResource::canViewAny())->toBeTrue()
        ->and(TaxExemptionResource::canCreate())->toBeTrue();
});

it('rejects an out-of-range default tax rate on settings save', function (): void {
    roundTwoGrant('tax.settings.manage');

    test()->actingAs(roundTwoUser('r2-tax-settings@example.com'));

    $page = new ManageTaxSettings;
    $page->data = roundTwoSettingsData(['defaultTaxRate' => 150]);

    try {
        $page->save();

        test()->fail('Expected settings save to reject an out-of-range rate.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.defaultTaxRate');
    }
});

it('rejects an unknown tax id label on settings save', function (): void {
    roundTwoGrant('tax.settings.manage');

    test()->actingAs(roundTwoUser('r2-tax-settings-label@example.com'));

    $page = new ManageTaxSettings;
    $page->data = roundTwoSettingsData(['taxIdLabel' => 'Not A Real Label']);

    try {
        $page->save();

        test()->fail('Expected settings save to reject an unknown label.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.taxIdLabel');
    }
});

it('accepts valid settings state', function (): void {
    roundTwoGrant('tax.settings.manage');

    test()->actingAs(roundTwoUser('r2-tax-settings-valid@example.com'));

    $page = new ManageTaxSettings;
    $page->data = roundTwoSettingsData();

    $state = $page->getSchema('form')->getState();

    expect($state['defaultTaxRate'])->toEqual(6.0)
        ->and($state['taxIdLabel'])->toBe('SST Number');
});

it('scopes exemptable customer search to the current owner', function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);

    $ownerA = roundTwoUser('r2-exempt-owner-a@example.com');
    $ownerB = roundTwoUser('r2-exempt-owner-b@example.com');

    roundTwoBindOwner($ownerA);
    $customerA = Customer::query()->create(['first_name' => 'Ade', 'last_name' => 'Searchtest']);

    roundTwoBindOwner($ownerB);
    Customer::query()->create(['first_name' => 'Ben', 'last_name' => 'Searchtest']);

    roundTwoBindOwner($ownerA);

    [$host, $schema] = roundTwoExemptionForm();
    $schema->fill(['exemptable_type' => Customer::class]);

    $select = roundTwoFindComponent($schema->getComponents(), 'exemptable_id');

    expect($select)->toBeInstanceOf(Select::class);

    $results = $select->getSearchResults('Searchtest');

    expect($results)->toHaveKey((string) $customerA->getKey())
        ->and($results)->toHaveCount(1);
});

it('scopes exemptable customer group search to the current owner', function (): void {
    config()->set('customers.features.owner.enabled', true);
    config()->set('customers.features.owner.include_global', false);

    $ownerA = roundTwoUser('r2-group-owner-a@example.com');
    $ownerB = roundTwoUser('r2-group-owner-b@example.com');

    roundTwoBindOwner($ownerA);
    $groupA = CustomerGroup::query()->create(['name' => 'Searchtest Group A']);

    roundTwoBindOwner($ownerB);
    CustomerGroup::query()->create(['name' => 'Searchtest Group B']);

    roundTwoBindOwner($ownerA);

    [$host, $schema] = roundTwoExemptionForm();
    $schema->fill(['exemptable_type' => CustomerGroup::class]);

    $select = roundTwoFindComponent($schema->getComponents(), 'exemptable_id');

    expect($select)->toBeInstanceOf(Select::class);

    $results = $select->getSearchResults('Searchtest Group');

    expect($results)->toHaveKey((string) $groupA->getKey())
        ->and($results)->toHaveCount(1);
});

it('rejects duplicate certificate numbers within the same owner', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = roundTwoUser('r2-cert-owner-a@example.com');
    roundTwoBindOwner($ownerA);

    $customer = Customer::query()->create(['first_name' => 'Cert', 'last_name' => 'Holder']);

    TaxExemption::query()->create([
        'exemptable_type' => Customer::class,
        'exemptable_id' => $customer->getKey(),
        'reason' => 'Existing',
        'status' => 'approved',
        'certificate_number' => 'R2-DUP-1',
    ]);

    [$host, $schema] = roundTwoExemptionForm();
    $schema->fill([
        'exemptable_type' => Customer::class,
        'exemptable_id' => (string) $customer->getKey(),
        'certificate_number' => 'R2-DUP-1',
        'reason' => 'Duplicate',
        'status' => 'pending',
    ]);

    try {
        $schema->getState();

        test()->fail('Expected a duplicate certificate number to fail validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('data.certificate_number');
    }
});

it('allows duplicate certificate numbers across owners', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = roundTwoUser('r2-cert-xowner-a@example.com');
    $ownerB = roundTwoUser('r2-cert-xowner-b@example.com');

    roundTwoBindOwner($ownerA);

    $customer = Customer::query()->create(['first_name' => 'Cert', 'last_name' => 'Across']);

    TaxExemption::query()->create([
        'exemptable_type' => Customer::class,
        'exemptable_id' => $customer->getKey(),
        'reason' => 'Existing',
        'status' => 'approved',
        'certificate_number' => 'R2-DUP-2',
    ]);

    roundTwoBindOwner($ownerB);

    $ownCustomer = Customer::query()->create(['first_name' => 'Cert', 'last_name' => 'Bee']);

    [$host, $schema] = roundTwoExemptionForm();
    $schema->fill([
        'exemptable_type' => Customer::class,
        'exemptable_id' => (string) $ownCustomer->getKey(),
        'certificate_number' => 'R2-DUP-2',
        'reason' => 'Cross owner',
        'status' => 'pending',
    ]);

    $state = $schema->getState();

    expect($state['certificate_number'])->toBe('R2-DUP-2');
});

it('blocks cross-owner exemption approval from the row action', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    roundTwoGrant('tax.exemptions.approve');

    test()->actingAs(roundTwoUser('r2-exempt-actor@example.com'));

    $ownerA = roundTwoUser('r2-approve-owner-a@example.com');
    $ownerB = roundTwoUser('r2-approve-owner-b@example.com');

    roundTwoBindOwner($ownerB);

    $class = TaxClass::query()->create(['name' => 'Std', 'slug' => 'r2-std', 'is_active' => true]);

    $foreign = TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->getKey(),
        'reason' => 'Foreign',
        'status' => 'pending',
    ]);

    roundTwoBindOwner($ownerA);

    $host = new RoundTwoTaxTableHost;
    $table = TaxExemptionsTable::configure(Table::make($host));

    try {
        roundTwoFindAction($table, 'approve')->livewire($host)->record($foreign)->call();

        test()->fail('Expected cross-owner approval to be rejected.');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('owner scope');
    }
});

it('approves an owned exemption from the row action', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    roundTwoGrant('tax.exemptions.approve');

    test()->actingAs(roundTwoUser('r2-exempt-approver@example.com'));

    roundTwoBindOwner(roundTwoUser('r2-approve-own@example.com'));

    $class = TaxClass::query()->create(['name' => 'Std Own', 'slug' => 'r2-std-own', 'is_active' => true]);

    $exemption = TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->getKey(),
        'reason' => 'Own',
        'status' => 'pending',
    ]);

    $host = new RoundTwoTaxTableHost;
    $table = TaxExemptionsTable::configure(Table::make($host));

    roundTwoFindAction($table, 'approve')->livewire($host)->record($exemption)->call();

    expect($exemption->fresh()->isApproved())->toBeTrue();
});

it('blocks cross-owner exemption renew and delete from row actions', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    roundTwoGrant('tax.exemptions.renew', 'tax.exemptions.delete');

    test()->actingAs(roundTwoUser('r2-exempt-renewer@example.com'));

    $ownerA = roundTwoUser('r2-renew-owner-a@example.com');
    $ownerB = roundTwoUser('r2-renew-owner-b@example.com');

    roundTwoBindOwner($ownerB);

    $class = TaxClass::query()->create(['name' => 'Std Renew', 'slug' => 'r2-std-renew', 'is_active' => true]);

    $foreign = TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->getKey(),
        'reason' => 'Foreign',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    roundTwoBindOwner($ownerA);

    $host = new RoundTwoTaxTableHost;
    $table = TaxExemptionsTable::configure(Table::make($host));

    try {
        roundTwoFindAction($table, 'renew')->livewire($host)->record($foreign)->call([
            'data' => ['new_expires_at' => '2030-06-01'],
        ]);

        test()->fail('Expected cross-owner renew to be rejected.');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('owner scope');
    }

    try {
        roundTwoFindAction($table, 'delete')->livewire($host)->record($foreign)->call();

        test()->fail('Expected cross-owner delete to be rejected.');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('owner scope');
    }

    expect($foreign->fresh())->not->toBeNull();
});

it('eager loads exemption relations on the resource query', function (): void {
    config()->set('tax.features.owner.enabled', false);

    $eagerLoads = TaxExemptionResource::getEloquentQuery()->getEagerLoads();

    expect($eagerLoads)->toHaveKeys(['exemptable', 'taxZone']);
});

it('eager loads exemptable on the expiring widget query', function (): void {
    $reflection = new ReflectionClass(ExpiringExemptionsWidget::class);
    $method = $reflection->getMethod('getTableQuery');

    /** @var Builder $query */
    $query = $method->invoke($reflection->newInstanceWithoutConstructor());

    expect($query->getEagerLoads())->toHaveKey('exemptable');
});

it('caches the expiring exemptions navigation badge', function (): void {
    config()->set('tax.features.owner.enabled', false);

    OwnerCache::forget(OwnerContext::resolve(), 'filament-tax.nav-badge.expiring-exemptions');

    TaxExemption::query()->delete();
    TaxClass::query()->delete();

    $class = TaxClass::query()->create(['name' => 'Badge', 'slug' => 'r2-badge', 'is_active' => true]);

    TaxExemption::query()->create([
        'exemptable_type' => TaxClass::class,
        'exemptable_id' => $class->getKey(),
        'reason' => 'Badge',
        'status' => 'approved',
        'expires_at' => now()->addDays(10),
    ]);

    expect(TaxExemptionResource::getNavigationBadge())->toBe('1');

    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(TaxExemptionResource::getNavigationBadge())->toBe('1')
        ->and(DB::getQueryLog())->toBeEmpty();

    DB::disableQueryLog();

    OwnerCache::forget(OwnerContext::resolve(), 'filament-tax.nav-badge.expiring-exemptions');
});
