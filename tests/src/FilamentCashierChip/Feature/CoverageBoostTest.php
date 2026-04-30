<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentCashierChip\TestCase;
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;
use AIArmada\FilamentCashierChip\FilamentCashierChipServiceProvider;
use AIArmada\FilamentCashierChip\Pages\BillingDashboard;
use AIArmada\FilamentCashierChip\Resources\BaseCashierChipResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages\ListCustomers;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Pages\ViewCustomer;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers\PaymentMethodsRelationManager;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\RelationManagers\SubscriptionsRelationManager;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Schemas\CustomerInfolist;
use AIArmada\FilamentCashierChip\Resources\CustomerResource\Tables\CustomerTable;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ListInvoices;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Pages\ViewInvoice;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Schemas\InvoiceInfolist;
use AIArmada\FilamentCashierChip\Resources\InvoiceResource\Tables\InvoiceTable;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages\ListSubscriptions;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages\ViewSubscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\RelationManagers\SubscriptionItemsRelationManager;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Schemas\SubscriptionInfolist;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Tables\SubscriptionTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component as LivewireComponent;
use Mockery\MockInterface;
use Spatie\LaravelPackageTools\Package;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

if (! function_exists('filamentCashierChip_makeSchemaLivewire')) {
    function filamentCashierChip_makeSchemaLivewire(): LivewireComponent & HasSchemas
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

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = [],
            ): Component | Action | ActionGroup | null {
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

function makeCashierChipTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return Table::make($livewire);
}

it('configures the package and registers the plugin singleton', function (): void {
    /** @var Package&MockInterface $package */
    $package = Mockery::mock(Package::class);
    $package->shouldReceive('name')->once()->with('filament-cashier-chip')->andReturnSelf();
    $package->shouldReceive('hasConfigFile')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasViews')->once()->withNoArgs()->andReturnSelf();
    $package->shouldReceive('hasTranslations')->once()->withNoArgs()->andReturnSelf();

    (new FilamentCashierChipServiceProvider(app()))->configurePackage($package);

    app()->register(FilamentCashierChipServiceProvider::class);

    expect(app()->bound(FilamentCashierChipPlugin::class))->toBeTrue();
    expect(app(FilamentCashierChipPlugin::class))->toBeInstanceOf(FilamentCashierChipPlugin::class);
});

it('registers resources, widgets, and pages based on feature flags', function (): void {
    config()->set('filament-cashier-chip.features.subscriptions', true);
    config()->set('filament-cashier-chip.features.customers', true);
    config()->set('filament-cashier-chip.features.invoices', true);
    config()->set('filament-cashier-chip.features.dashboard_widgets', true);

    /** @var Panel&MockInterface $panel */
    $panel = Mockery::mock(Panel::class);

    // @phpstan-ignore method.notFound
    $panel->shouldReceive('getId')->andReturn('admin');
    // @phpstan-ignore method.notFound
    $panel->shouldReceive('hasPlugin')->with('filament-cashier')->andReturn(false);
    // @phpstan-ignore method.notFound
    $panel->shouldReceive('resources')->once()->andReturnSelf();
    // @phpstan-ignore method.notFound
    $panel->shouldReceive('widgets')->once()->andReturnSelf();
    // @phpstan-ignore method.notFound
    $panel->shouldReceive('pages')->once()->andReturnSelf();

    // @phpstan-ignore argument.type
    (new FilamentCashierChipPlugin)->register($panel);
});

it('builds resources, schemas, tables, pages, and relation managers', function (): void {
    $schemaLivewire = filamentCashierChip_makeSchemaLivewire();

    // Resource classes
    foreach ([CustomerResource::class, SubscriptionResource::class, InvoiceResource::class] as $resource) {
        expect($resource::table(makeCashierChipTable()))->toBeInstanceOf(Table::class);
        expect($resource::infolist(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);
        expect($resource::getPages())->toBeArray();
        expect($resource::getGloballySearchableAttributes())->toBeArray();
    }

    // Builder classes
    expect(CustomerTable::configure(makeCashierChipTable()))->toBeInstanceOf(Table::class);
    expect(CustomerInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    expect(SubscriptionTable::configure(makeCashierChipTable()))->toBeInstanceOf(Table::class);
    expect(SubscriptionInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    expect(InvoiceTable::configure(makeCashierChipTable()))->toBeInstanceOf(Table::class);
    expect(InvoiceInfolist::configure(Schema::make($schemaLivewire)))->toBeInstanceOf(Schema::class);

    // Base resource helpers
    expect(CustomerResource::getNavigationBadge())->toBeNull();
    expect(CustomerResource::getNavigationBadgeColor())->toBeString();
    expect(CustomerResource::getNavigationGroup())->toBeString();
    expect(CustomerResource::getNavigationSort())->toBeInt();

    $formatted = (function (): string {
        $method = new ReflectionMethod(BaseCashierChipResource::class, 'formatAmount');

        return $method->invoke(null, 12345, 'MYR');
    })();

    expect($formatted)->toBe('MYR 123.45');

    // Pages (header actions via reflection, without needing records)
    foreach ([ListCustomers::class, ListSubscriptions::class, ListInvoices::class] as $pageClass) {
        $page = app($pageClass);
        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        expect($method->invoke($page))->toBeArray();
    }

    foreach ([ViewCustomer::class, ViewSubscription::class, ViewInvoice::class] as $pageClass) {
        $page = app($pageClass);
        $method = new ReflectionMethod($pageClass, 'getHeaderActions');
        expect($method->invoke($page))->toBeArray();
    }

    // Dashboard page
    $dashboard = app(BillingDashboard::class);
    expect($dashboard->getHeaderWidgets())->toBeArray();
    expect($dashboard->getFooterWidgets())->toBeArray();

    // Relation managers
    foreach ([
        PaymentMethodsRelationManager::class,
        SubscriptionsRelationManager::class,
        SubscriptionItemsRelationManager::class,
    ] as $managerClass) {
        $instance = app($managerClass);
        expect($instance->table(makeCashierChipTable()))->toBeInstanceOf(Table::class);
    }
});
