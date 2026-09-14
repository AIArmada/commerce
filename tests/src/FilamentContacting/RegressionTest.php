<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Enums\ContactPurpose;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\ContactSnapshot;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentContacting\FilamentContactingPlugin;
use AIArmada\FilamentContacting\Imports\ContactMethodImporter;
use AIArmada\FilamentContacting\Imports\SocialProfileImporter;
use AIArmada\FilamentContacting\RelationManagers\ContactMethodsRelationManager;
use AIArmada\FilamentContacting\RelationManagers\SocialProfilesRelationManager;
use AIArmada\FilamentContacting\Resources\ContactMethodResource;
use AIArmada\FilamentContacting\Resources\ContactMethodResource\Pages\ListContactMethods;
use AIArmada\FilamentContacting\Resources\ContactSnapshotResource;
use AIArmada\FilamentContacting\Resources\SocialProfileResource;
use AIArmada\FilamentContacting\Resources\SocialProfileResource\Pages\ListSocialProfiles;
use AIArmada\FilamentContacting\Schemas\ContactMethodFormSchema;
use AIArmada\FilamentContacting\Schemas\SocialProfileFormSchema;
use AIArmada\FilamentContacting\Schemas\SocialProfileInfolistSchema;
use AIArmada\FilamentContacting\Support\ContactingLinks;
use AIArmada\FilamentContacting\Support\ContactingRelationOwnerScope;
use AIArmada\FilamentContacting\Tables\ContactMethodTable;
use AIArmada\FilamentContacting\Tables\ContactSnapshotTable;
use AIArmada\FilamentContacting\Tables\SocialProfileTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContactingRepairSchemaHost extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function makeContactingTestTable(string $builder): Table
{
    return $builder::table(Table::make(Mockery::mock(HasTable::class)));
}

function makeContactingTestOwner(): User
{
    return User::factory()->create();
}

function makeContactingTestCustomer(User $owner, string $prefix): Customer
{
    config()->set('customers.features.owner.enabled', true);

    return OwnerContext::withOwner($owner, fn (): Customer => Customer::query()->create([
        'first_name' => 'Owner',
        'last_name' => $prefix,
        'email' => "contacting-{$prefix}-" . uniqid() . '@example.com',
        'status' => 'active',
    ]));
}

it('disables contact method table mutations when read-only', function (): void {
    config()->set('filament-contacting.resources.contact_methods.read_only', true);

    $table = makeContactingTestTable(ContactMethodTable::class);
    $actions = collect($table->getActions());

    expect($actions->first(fn ($action): bool => $action->getName() === 'edit')->record(new ContactMethod)->isDisabled())->toBeTrue();
    expect($actions->first(fn ($action): bool => $action->getName() === 'delete')->record(new ContactMethod)->isDisabled())->toBeTrue();
    expect(collect($table->getBulkActions())->first(fn ($action): bool => $action instanceof DeleteBulkAction)->isDisabled())->toBeTrue();

    config()->set('filament-contacting.resources.contact_methods.read_only', false);

    $table = makeContactingTestTable(ContactMethodTable::class);
    $actions = collect($table->getActions());

    expect($actions->first(fn ($action): bool => $action->getName() === 'edit')->record(new ContactMethod)->isDisabled())->toBeFalse();
    expect($actions->first(fn ($action): bool => $action->getName() === 'delete')->record(new ContactMethod)->isDisabled())->toBeFalse();
});

it('disables social profile table mutations when read-only', function (): void {
    config()->set('filament-contacting.resources.social_profiles.read_only', true);

    $table = makeContactingTestTable(SocialProfileTable::class);
    $actions = collect($table->getActions());

    expect($actions->first(fn ($action): bool => $action->getName() === 'edit')->record(new SocialProfile)->isDisabled())->toBeTrue();
    expect($actions->first(fn ($action): bool => $action->getName() === 'delete')->record(new SocialProfile)->isDisabled())->toBeTrue();
    expect(collect($table->getBulkActions())->first(fn ($action): bool => $action instanceof DeleteBulkAction)->isDisabled())->toBeTrue();

    config()->set('filament-contacting.resources.social_profiles.read_only', false);

    $table = makeContactingTestTable(SocialProfileTable::class);
    $actions = collect($table->getActions());

    expect($actions->first(fn ($action): bool => $action->getName() === 'edit')->record(new SocialProfile)->isDisabled())->toBeFalse();
    expect($actions->first(fn ($action): bool => $action->getName() === 'delete')->record(new SocialProfile)->isDisabled())->toBeFalse();
});

it('disables relation manager creates when read-only', function (): void {
    config()->set('filament-contacting.resources.contact_methods.read_only', true);
    config()->set('filament-contacting.resources.social_profiles.read_only', true);

    $methodsTable = app(ContactMethodsRelationManager::class)->table(Table::make(Mockery::mock(HasTable::class)));
    $profilesTable = app(SocialProfilesRelationManager::class)->table(Table::make(Mockery::mock(HasTable::class)));

    expect(collect($methodsTable->getHeaderActions())->first(fn ($action): bool => $action instanceof CreateAction)->isDisabled())->toBeTrue();
    expect(collect($profilesTable->getHeaderActions())->first(fn ($action): bool => $action instanceof CreateAction)->isDisabled())->toBeTrue();
});

it('denies resource mutations when read-only', function (): void {
    config()->set('filament-contacting.resources.contact_methods.read_only', true);
    config()->set('filament-contacting.resources.social_profiles.read_only', false);

    expect(ContactMethodResource::canEdit(new ContactMethod))->toBeFalse();
    expect(ContactMethodResource::canDelete(new ContactMethod))->toBeFalse();
    expect(ContactMethodResource::canDeleteAny())->toBeFalse();
    expect(SocialProfileResource::canEdit(new SocialProfile))->toBeTrue();
    expect(SocialProfileResource::canDelete(new SocialProfile))->toBeTrue();
    expect(SocialProfileResource::canDeleteAny())->toBeTrue();

    expect(ContactSnapshotResource::canCreate())->toBeFalse();
    expect(ContactSnapshotResource::canEdit(new ContactSnapshot))->toBeFalse();
    expect(ContactSnapshotResource::canDelete(new ContactSnapshot))->toBeFalse();
    expect(ContactSnapshotResource::canDeleteAny())->toBeFalse();
});

it('standalone resources never offer creates', function (): void {
    config()->set('filament-contacting.resources.contact_methods.read_only', false);
    config()->set('filament-contacting.resources.social_profiles.read_only', false);

    expect(ContactMethodResource::getPages())->not->toHaveKey('create');
    expect(SocialProfileResource::getPages())->toHaveKeys(['index', 'view', 'edit']);
    expect(SocialProfileResource::getPages())->not->toHaveKey('create');
    expect(ContactMethodResource::getPages())->toHaveKeys(['index', 'view', 'edit']);
    expect(ContactMethodResource::canCreate())->toBeFalse();
    expect(SocialProfileResource::canCreate())->toBeFalse();

    config()->set('filament-contacting.resources.contact_methods.read_only', true);

    expect(ContactMethodResource::getPages())->not->toHaveKey('edit');
});

it('blank is_public cells fall back to core private-by-default', function (): void {
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'a');

    $columnMap = [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
        'is_public' => 'is_public',
    ];

    $base = [
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
    ];

    $blank = 'blank-' . uniqid() . '@example.com';
    $explicitTrue = 'explicittrue-' . uniqid() . '@example.com';
    $explicitFalse = 'explicitfalse-' . uniqid() . '@example.com';

    OwnerContext::withOwner($owner, function () use ($columnMap, $base, $blank, $explicitTrue, $explicitFalse): void {
        (new ContactMethodImporter(new Import, $columnMap, []))($base + ['value' => $blank, 'is_public' => '']);
        (new ContactMethodImporter(new Import, $columnMap, []))($base + ['value' => $explicitTrue, 'is_public' => 'true']);
        (new ContactMethodImporter(new Import, $columnMap, []))($base + ['value' => $explicitFalse, 'is_public' => 'false']);
    });

    $flags = OwnerContext::withOwner($owner, fn (): array => ContactMethod::query()
        ->where('contactable_id', $customer->getKey())
        ->pluck('is_public', 'value')
        ->all());

    expect((bool) $flags[$blank])->toBeFalse();
    expect((bool) $flags[$explicitTrue])->toBeTrue();
    expect((bool) $flags[$explicitFalse])->toBeFalse();
    expect(count($flags))->toBe(3);
});

it('rejects contact method rows that violate form constraints', function (): void {
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'b');

    $columnMap = [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
        'country_code' => 'country_code',
    ];

    $base = [
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
    ];

    $run = function (array $row) use ($columnMap, $owner): void {
        OwnerContext::withOwner($owner, fn (): mixed => (new ContactMethodImporter(new Import, $columnMap, []))($row));
    };

    expect(fn () => $run($base + ['type' => 'pigeon', 'value' => 'coo']))->toThrow(ValidationException::class);
    expect(fn () => $run($base + ['type' => 'email', 'value' => 'ok-' . uniqid() . '@example.com', 'country_code' => 'USA']))->toThrow(ValidationException::class);
    expect(fn () => $run($base + ['type' => 'email', 'value' => 'ok-' . uniqid() . '@example.com', 'country_code' => 'M1']))->toThrow(ValidationException::class);
    expect(fn () => $run($base + ['type' => 'email', 'value' => str_repeat('a', 2050)]))->toThrow(ValidationException::class);

    OwnerContext::withOwner($owner, fn (): mixed => (new ContactMethodImporter(new Import, $columnMap, []))($base + [
        'type' => 'email',
        'value' => 'valid-' . uniqid() . '@example.com',
        'country_code' => 'my',
    ]));

    expect(OwnerContext::withOwner($owner, fn (): int => ContactMethod::query()->where('contactable_id', $customer->getKey())->count()))->toBe(1);
});

it('rejects social profile rows that violate form constraints', function (): void {
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'c');

    $columnMap = [
        'socialable_type' => 'socialable_type',
        'socialable_id' => 'socialable_id',
        'platform' => 'platform',
        'handle' => 'handle',
        'url' => 'url',
    ];

    $base = [
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
    ];

    $run = function (array $row) use ($columnMap, $owner): void {
        OwnerContext::withOwner($owner, fn (): mixed => (new SocialProfileImporter(new Import, $columnMap, []))($row));
    };

    expect(fn () => $run($base + ['platform' => 'myspace-2', 'handle' => 'x']))->toThrow(ValidationException::class);
    expect(fn () => $run($base + ['platform' => 'facebook', 'handle' => 'x', 'url' => 'javascript:alert(1)']))->toThrow(ValidationException::class);
    expect(fn () => $run($base + ['platform' => 'facebook', 'handle' => '', 'url' => '']))->toThrow(ValidationException::class);

    OwnerContext::withOwner($owner, fn (): mixed => (new SocialProfileImporter(new Import, $columnMap, []))($base + [
        'platform' => 'facebook',
        'handle' => 'valid-' . uniqid(),
    ]));

    expect(OwnerContext::withOwner($owner, fn (): int => SocialProfile::query()->where('socialable_id', $customer->getKey())->count()))->toBe(1);
});

it('rejects social imports with neither handle nor url mapped', function (): void {
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'd');

    $importer = new SocialProfileImporter(new Import, [
        'socialable_type' => 'socialable_type',
        'socialable_id' => 'socialable_id',
        'platform' => 'platform',
    ], []);

    expect(fn () => OwnerContext::withOwner($owner, fn (): mixed => $importer([
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
        'platform' => 'facebook',
    ])))->toThrow(RowImportFailedException::class, 'Either a handle or a URL is required.');
});

it('reports invalid parent references with a descriptive reason', function (): void {
    // Invalid reference shapes get a row message; missing/inaccessible records stay
    // AuthorizationException so imports cannot probe record existence across owners.
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'e');

    $methodImporter = new ContactMethodImporter(new Import, [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
    ], []);

    try {
        OwnerContext::withOwner($owner, fn (): mixed => $methodImporter([
            'contactable_type' => 'App\\Models\\Nope',
            'contactable_id' => '1',
            'type' => 'email',
            'value' => 'x@example.com',
        ]));

        $this->fail('Expected RowImportFailedException for an invalid contactable type.');
    } catch (RowImportFailedException $exception) {
        expect($exception->getMessage())->toContain('model reference type');
        expect($exception->getPrevious())->toBeInstanceOf(InvalidArgumentException::class);
    }

    $profileImporter = new SocialProfileImporter(new Import, [
        'socialable_type' => 'socialable_type',
        'socialable_id' => 'socialable_id',
        'platform' => 'platform',
        'handle' => 'handle',
    ], []);

    expect(fn () => OwnerContext::withOwner($owner, fn (): mixed => $profileImporter([
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => '00000000-0000-0000-0000-000000000000',
        'platform' => 'facebook',
        'handle' => 'ghost',
    ])))->toThrow(AuthorizationException::class);

    expect(OwnerContext::withOwner($owner, fn (): int => ContactMethod::query()->where('contactable_id', $customer->getKey())->count()
        + SocialProfile::query()->where('socialable_id', $customer->getKey())->count()))->toBe(0);
});

it('importers use human-readable model labels', function (): void {
    expect(ContactMethodImporter::getModelLabel())->toBe('Contact Method');
    expect(SocialProfileImporter::getModelLabel())->toBe('Social Profile');
});

it('importers are insert-only', function (): void {
    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'f');

    $methodImporter = new ContactMethodImporter(new Import, [
        'contactable_type' => 'contactable_type',
        'contactable_id' => 'contactable_id',
        'type' => 'type',
        'value' => 'value',
    ], []);

    expect($methodImporter->resolveRecord())->toBeInstanceOf(ContactMethod::class);
    expect($methodImporter->resolveRecord()->exists)->toBeFalse();

    $profileImporter = new SocialProfileImporter(new Import, [
        'socialable_type' => 'socialable_type',
        'socialable_id' => 'socialable_id',
        'platform' => 'platform',
        'handle' => 'handle',
    ], []);

    expect($profileImporter->resolveRecord())->toBeInstanceOf(SocialProfile::class);
    expect($profileImporter->resolveRecord()->exists)->toBeFalse();

    $row = [
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
        'value' => 'twice-' . uniqid() . '@example.com',
    ];

    OwnerContext::withOwner($owner, fn (): mixed => $methodImporter($row));

    expect(fn () => OwnerContext::withOwner($owner, fn (): mixed => $methodImporter($row)))
        ->toThrow(RowImportFailedException::class, 'already exists and imports never update existing records');
    expect(OwnerContext::withOwner($owner, fn (): int => ContactMethod::query()->where('contactable_id', $customer->getKey())->count()))->toBe(1);
});

it('relation owner scope passes through parents without owner columns', function (): void {
    $user = makeContactingTestOwner();

    expect(method_exists($user, 'ownerScopeConfig'))->toBeFalse();
    expect(ContactingRelationOwnerScope::canAccessParent($user))->toBeTrue();

    $query = ContactMethod::query();

    expect(ContactingRelationOwnerScope::scopeForParent($query, $user))->toBe($query);
});

it('relation owner scope fails closed on cross-owner parents', function (): void {
    $ownerA = makeContactingTestOwner();
    $ownerB = makeContactingTestOwner();
    $customerB = makeContactingTestCustomer($ownerB, 'g');

    OwnerContext::withOwner($ownerB, fn (): ContactMethod => $customerB->addContactMethod(
        ContactMethodData::email('scoped-' . uniqid() . '@example.com')
    ));

    expect(OwnerContext::withOwner($ownerA, fn (): bool => ContactingRelationOwnerScope::canAccessParent($customerB)))->toBeFalse();
    expect(OwnerContext::withOwner($ownerB, fn (): bool => ContactingRelationOwnerScope::canAccessParent($customerB)))->toBeTrue();

    $scoped = OwnerContext::withOwner(
        $ownerA,
        fn (): int => ContactingRelationOwnerScope::scopeForParent(ContactMethod::query(), $customerB)->count(),
    );

    expect($scoped)->toBe(0);

    $sameOwner = OwnerContext::withOwner(
        $ownerB,
        fn (): int => ContactingRelationOwnerScope::scopeForParent(ContactMethod::query(), $customerB)->count(),
    );

    expect($sameOwner)->toBe(1);
});

it('relation manager table refuses cross-owner parents', function (): void {
    $ownerA = makeContactingTestOwner();
    $ownerB = makeContactingTestOwner();
    $customerB = makeContactingTestCustomer($ownerB, 'h');

    OwnerContext::withOwner($ownerB, fn (): ContactMethod => $customerB->addContactMethod(
        ContactMethodData::email('rm-' . uniqid() . '@example.com')
    ));

    $manager = app(ContactMethodsRelationManager::class);
    $manager->ownerRecord = $customerB;
    $table = $manager->table(Table::make(Mockery::mock(HasTable::class)));

    $count = OwnerContext::withOwner(
        $ownerA,
        fn (): int => $table->applyQueryScopes(ContactMethod::query())->count(),
    );

    expect($count)->toBe(0);

    $create = collect($table->getHeaderActions())->first(fn ($action): bool => $action instanceof CreateAction);

    expect(fn () => OwnerContext::withOwner($ownerA, fn (): mixed => $create->callBefore()))->toThrow(HttpException::class);
});

it('snapshot and country filters offer usable choices', function (): void {
    $table = makeContactingTestTable(ContactSnapshotTable::class);

    $typeFilter = $table->getFilter('snapshot_type');

    expect($typeFilter)->toBeInstanceOf(SelectFilter::class);
    expect($typeFilter->getOptions())->toMatchArray([
        'contact_method' => 'Contact Method',
        'social_profile' => 'Social Profile',
    ]);

    $channelFilter = $table->getFilter('channel');

    expect($channelFilter)->toBeInstanceOf(SelectFilter::class);
    expect($channelFilter->getOptions())->not->toBeEmpty();
    expect($channelFilter->getOptions())->toHaveKeys(['email', 'facebook']);

    $reasonFilter = $table->getFilter('reason');

    expect($reasonFilter)->toBeInstanceOf(Filter::class);
    expect($reasonFilter)->not->toBeInstanceOf(SelectFilter::class);

    $owner = makeContactingTestOwner();
    $customer = makeContactingTestCustomer($owner, 'i');

    OwnerContext::withOwner($owner, fn (): ContactMethod => $customer->addContactMethod(
        ContactMethodData::email('filter-' . uniqid() . '@example.com')
    ));

    $methods = makeContactingTestTable(ContactMethodTable::class);
    $countryFilter = $methods->getFilter('country_code');

    expect($countryFilter)->toBeInstanceOf(Filter::class);
    expect($countryFilter)->not->toBeInstanceOf(SelectFilter::class);
    expect(collect($countryFilter->getFormSchema())->first(fn ($field): bool => $field instanceof TextInput))->not->toBeNull();
});

it('tables honor the configured default pagination', function (): void {
    foreach ([ContactMethodTable::class, SocialProfileTable::class, ContactSnapshotTable::class] as $builder) {
        $table = makeContactingTestTable($builder);

        expect($table->getDefaultPaginationPageOption())->toBe(25);
        expect($table->getPaginationPageOptions())->toContain(25);
    }

    config()->set('filament-contacting.tables.default_pagination', 100);

    $table = makeContactingTestTable(ContactMethodTable::class);

    expect($table->getDefaultPaginationPageOption())->toBe(100);
    expect($table->getPaginationPageOptions())->toContain(100);
});

it('social url column links http urls when enabled', function (): void {
    config()->set('filament-contacting.features.open_url_actions', true);

    $table = makeContactingTestTable(SocialProfileTable::class);
    $column = $table->getColumn('url');

    expect($column->getUrl('https://example.com/x'))->toBe('https://example.com/x');
    expect($column->getUrl('javascript:alert(1)'))->toBeNull();
    expect($column->getUrl(null))->toBeNull();

    config()->set('filament-contacting.features.open_url_actions', false);

    $table = makeContactingTestTable(SocialProfileTable::class);

    expect($table->getColumn('url')->getUrl('https://example.com/x'))->toBeNull();
});

it('owner and verification columns follow config', function (): void {
    $table = makeContactingTestTable(ContactMethodTable::class);

    expect($table->getColumn('owner_type'))->not->toBeNull();
    expect($table->getColumn('owner_type')->isVisible())->toBeFalse();
    expect($table->getColumn('owner_id')->isVisible())->toBeFalse();
    expect($table->getColumn('is_verified')->isVisible())->toBeTrue();

    config()->set('filament-contacting.tables.show_owner_columns', true);
    config()->set('filament-contacting.features.verification_badges', false);

    $table = makeContactingTestTable(ContactMethodTable::class);

    expect($table->getColumn('owner_type')->isVisible())->toBeTrue();
    expect($table->getColumn('owner_id')->isVisible())->toBeTrue();
    expect($table->getColumn('is_verified')->isVisible())->toBeFalse();

    $snapshots = makeContactingTestTable(ContactSnapshotTable::class);

    expect($snapshots->getColumn('owner_type')->isVisible())->toBeTrue();
});

it('list pages wire imports only when enabled and writable', function (): void {
    $headers = function (string $page): array {
        $method = new ReflectionMethod($page, 'getHeaderActions');
        $method->setAccessible(true);

        return $method->invoke(app($page));
    };

    config()->set('filament-contacting.features.imports', false);

    expect($headers(ListContactMethods::class))->toBe([]);
    expect($headers(ListSocialProfiles::class))->toBe([]);

    config()->set('filament-contacting.features.imports', true);
    config()->set('filament-contacting.resources.contact_methods.read_only', true);

    expect($headers(ListContactMethods::class))->toBe([]);

    $actions = $headers(ListSocialProfiles::class);

    expect($actions)->toHaveCount(1);
    expect($actions[0])->toBeInstanceOf(ImportAction::class);
    expect($actions[0]->getImporter())->toBe(SocialProfileImporter::class);

    config()->set('filament-contacting.resources.contact_methods.read_only', false);

    $actions = $headers(ListContactMethods::class);

    expect($actions)->toHaveCount(1);
    expect($actions[0]->getImporter())->toBe(ContactMethodImporter::class);
});

it('plugin requires the standalone resources master switch', function (): void {
    config()->set('filament-contacting.features.standalone_resources', false);
    config()->set('filament-contacting.resources.contact_methods.enabled', true);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->never();

    FilamentContactingPlugin::make()->register($panel);

    config()->set('filament-contacting.features.standalone_resources', true);
    config()->set('filament-contacting.resources.social_profiles.enabled', false);
    config()->set('filament-contacting.resources.contact_snapshots.enabled', false);

    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('resources')->once()->with([ContactMethodResource::class])->andReturnSelf();

    FilamentContactingPlugin::make()->register($panel);
});

it('docs use the correct namespace casing and filament version', function (): void {
    $root = dirname(__DIR__, 3);
    $installation = (string) file_get_contents($root . '/packages/filament-contacting/docs/02-installation.md');
    $usage = (string) file_get_contents($root . '/packages/filament-contacting/docs/04-usage.md');
    $composer = json_decode((string) file_get_contents($root . '/packages/filament-contacting/composer.json'), true);

    expect($installation)->not->toContain('AiArmada\\');
    expect($usage)->not->toContain('AiArmada\\');
    expect($installation)->toContain($composer['require']['filament/filament']);
});

it('infolist links only safe urls', function (): void {
    config()->set('filament-contacting.features.open_url_actions', true);

    $schema = Schema::make(new ContactingRepairSchemaHost)->components(SocialProfileInfolistSchema::make())->statePath('data');
    $entry = $schema->getComponent(
        fn ($component): bool => $component instanceof TextEntry && $component->getName() === 'url',
        withHidden: true,
    );

    expect($entry->getUrl('https://example.com/x'))->toBe('https://example.com/x');
    expect($entry->getUrl('javascript:alert(1)'))->toBeNull();

    expect(ContactingLinks::safeHttpUrl('HTTP://EXAMPLE.COM'))->toBe('HTTP://EXAMPLE.COM');
    expect(ContactingLinks::safeHttpUrl('ftp://example.com/x'))->toBeNull();
    expect(ContactingLinks::safeHttpUrl(''))->toBeNull();
    expect(ContactingLinks::safeHttpUrl(null))->toBeNull();
});

it('contact method values validate per type', function (): void {
    $validate = function (array $state): void {
        Schema::make(new ContactingRepairSchemaHost)->components(ContactMethodFormSchema::make())->statePath('data')->fill($state)->validate();
    };

    expect(fn () => $validate(['type' => 'email', 'value' => 'not-an-email']))->toThrow(ValidationException::class);
    $validate(['type' => 'email', 'value' => 'good@example.com']);

    expect(fn () => $validate(['type' => 'website', 'value' => 'not a url!!!']))->toThrow(ValidationException::class);
    $validate(['type' => 'website', 'value' => 'https://example.com']);
    $validate(['type' => 'website', 'value' => 'example.com']);

    expect(fn () => $validate(['type' => 'phone', 'value' => 'not a phone !!!']))->toThrow(ValidationException::class);
    $validate(['type' => 'phone', 'value' => '+60123456789']);

    expect(fn () => $validate(['type' => 'email', 'value' => 'good@example.com', 'country_code' => 'M1']))->toThrow(ValidationException::class);
    $validate(['type' => 'email', 'value' => 'good@example.com', 'country_code' => 'MY']);
});

it('social profiles require a handle or a url', function (): void {
    $validate = function (array $state): void {
        Schema::make(new ContactingRepairSchemaHost)->components(SocialProfileFormSchema::make())->statePath('data')->fill($state)->validate();
    };

    expect(fn () => $validate(['platform' => 'facebook']))->toThrow(ValidationException::class);
    $validate(['platform' => 'facebook', 'handle' => 'example']);
    $validate(['platform' => 'facebook', 'url' => 'https://facebook.com/example']);
});

it('drops the unused phone input dependency', function (): void {
    $root = dirname(__DIR__, 3);
    $composer = json_decode((string) file_get_contents($root . '/packages/filament-contacting/composer.json'), true);

    expect($composer['require'])->not->toHaveKey('ysfkaya/filament-phone-input');
});

it('exposes an optional purpose select on the contact method form', function (): void {
    $schema = Schema::make(new ContactingRepairSchemaHost)->components(ContactMethodFormSchema::make())->statePath('data');
    $select = $schema->getComponent(
        fn ($component): bool => $component instanceof Select && $component->getName() === 'purpose',
        withHidden: true,
    );

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->isRequired())->toBeFalse()
        ->and($select->getOptions())->toBe(ContactPurpose::options());
});

it('sets the contact method purpose from the form while keeping the general default', function (): void {
    $stateFor = function (array $input): array {
        $schema = Schema::make(new ContactingRepairSchemaHost)
            ->components(ContactMethodFormSchema::make())
            ->statePath('data')
            ->fill();

        foreach ($input as $name => $value) {
            $component = $schema->getComponent(
                fn ($component): bool => $component instanceof Field && $component->getName() === $name,
                withHidden: true,
            );

            $component->state($value);
        }

        return $schema->getState();
    };

    expect($stateFor(['type' => 'email', 'value' => 'purpose-default@example.com']))
        ->toMatchArray(['purpose' => 'general'])
        ->and($stateFor(['type' => 'email', 'value' => 'purpose-set@example.com', 'purpose' => 'support']))
        ->toMatchArray(['purpose' => 'support']);
});

it('exposes an optional purpose select on the social profile form', function (): void {
    $schema = Schema::make(new ContactingRepairSchemaHost)->components(SocialProfileFormSchema::make())->statePath('data');
    $select = $schema->getComponent(
        fn ($component): bool => $component instanceof Select && $component->getName() === 'purpose',
        withHidden: true,
    );

    expect($select)->toBeInstanceOf(Select::class)
        ->and($select->isRequired())->toBeFalse()
        ->and($select->getOptions())->toBe(ContactPurpose::options());
});

it('sets the social profile purpose from the form while keeping the general default', function (): void {
    $stateFor = function (array $input): array {
        $schema = Schema::make(new ContactingRepairSchemaHost)
            ->components(SocialProfileFormSchema::make())
            ->statePath('data')
            ->fill();

        foreach ($input as $name => $value) {
            $component = $schema->getComponent(
                fn ($component): bool => $component instanceof Field && $component->getName() === $name,
                withHidden: true,
            );

            $component->state($value);
        }

        return $schema->getState();
    };

    expect($stateFor(['platform' => 'facebook', 'handle' => 'purpose-default']))
        ->toMatchArray(['purpose' => 'general'])
        ->and($stateFor(['platform' => 'facebook', 'handle' => 'purpose-set', 'purpose' => 'media']))
        ->toMatchArray(['purpose' => 'media']);
});
