---
title: Customization
---

# Customization

This guide covers how to extend and customize the Filament Tax plugin.

## Extending Resources

### Replace Resource Classes

`AIArmada\FilamentTax\Resources\TaxZoneResource` is `final`, so it cannot be extended.
Write your own `Resource` instead and disable the built-in one:

```php
namespace App\Filament\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxZone;
use Filament\Resources\Resource;
use UnitEnum;

class TaxZoneResource extends Resource
{
    protected static ?string $model = TaxZone::class;
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-tax.navigation.group');
    }
    
    public static function getNavigationSort(): ?int
    {
        return config('filament-tax.resources.navigation_sort.zones');
    }
    
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }
}
```

Register in your panel:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->resources([
            App\Filament\Resources\TaxZoneResource::class,
        ])
        ->plugins([
            FilamentTaxPlugin::make()
                ->zones(false), // Disable built-in
        ]);
}
```

> **warning**
> Never set `protected static ?string $navigationGroup`. It is forbidden by the repo's
> Filament rules because it blocks the `CommerceNavigation` runtime override engine, which
> reads a resource's config default and then merges
> `commerce-support.filament.navigation.items.{FQCN}` on top. Always implement
> `getNavigationGroup()` and read `config('filament-tax.navigation.group')` inside it —
> otherwise `config/filament-tax.php` `navigation.group` changes have no effect.

### Custom Form Schema

The package form classes are `final` and expose `configure(Schema $schema): Schema`
(the v5 schema entry point), not a static `make(): array`. To add fields, write your
own `Filament\Schemas\Schema` and register it on the resource:

```php
namespace App\Filament\Resources\TaxZoneResource\Schemas;

use AIArmada\Tax\Models\TaxZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaxZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // ... the package schema from
                // AIArmada\FilamentTax\Resources\TaxZoneResource\Schemas\TaxZoneForm
                
                Select::make('tax_authority')
                    ->label('Tax Authority')
                    ->options([
                        'federal' => 'Federal',
                        'state' => 'State',
                        'local' => 'Local',
                    ]),
                
                TextInput::make('authority_id')
                    ->label('Authority ID'),
            ]);
    }
}
```

> **info**
> `getFormSchema()` and `getTableColumns()` were removed in Filament v4. Use the
> `form(Schema $schema): Schema` and `table(Table $table): Table` methods. `Section`
> lives in `Filament\Schemas\Components\Section`, not `Filament\Forms\Components\Section`.

### Custom Table Columns

Add columns through `table()`:

```php
namespace App\Filament\Resources\TaxZoneResource\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // ... the package columns
                
                TextColumn::make('tax_authority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'federal' => 'primary',
                        'state' => 'success',
                        'local' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('total_collected')
                    ->money('MYR')
                    ->label('Total Collected'),
            ]);
    }
}
```

> **info**
> `TextColumn::colors([...])` is a deprecated v3 alias. In v5 use `->color(...)`, which
> accepts a string, array, or closure.

## Custom Actions

### Add Resource Actions

```php
namespace App\Filament\Resources\TaxZoneResource\Pages;

use AIArmada\FilamentTax\Resources\TaxZoneResource\Pages\ListTaxZones as BaseListPage;
use Filament\Actions;

class ListTaxZones extends BaseListPage
{
    protected function getHeaderActions(): array
    {
        return array_merge(parent::getHeaderActions(), [
            Actions\Action::make('export')
                ->label('Export Zones')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportZones()),
                
            Actions\Action::make('import')
                ->label('Import Zones')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv']),
                ])
                ->action(fn (array $data) => $this->importZones($data)),
        ]);
    }
    
    protected function exportZones(): StreamedResponse
    {
        // Export implementation
    }
    
    protected function importZones(array $data): void
    {
        // Import implementation
    }
}
```

### Add Table Actions

Row actions in Filament v5 use `Filament\Actions\Action` and the `recordActions()`
method:

```php
use AIArmada\Tax\Models\TaxZone;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;

public function table(Table $table): Table
{
    return $table
        ->recordActions([
            Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(function (TaxZone $record) {
                    $new = $record->replicate();
                    $new->name = $record->name . ' (Copy)';
                    $new->code = $record->code . '_COPY';
                    $new->is_default = false;
                    $new->save();
                    
                    // Copy rates too
                    foreach ($record->rates as $rate) {
                        $newRate = $rate->replicate();
                        $newRate->zone_id = $new->id;
                        $newRate->save();
                    }
                }),
                
            Action::make('test')
                ->icon('heroicon-o-beaker')
                ->schema([
                    TextInput::make('country')->required(),
                    TextInput::make('state'),
                    TextInput::make('postcode'),
                ])
                ->action(function (TaxZone $record, array $data) {
                    $matches = $record->matchesAddress(
                        $data['country'],
                        $data['state'],
                        $data['postcode']
                    );
                    
                    Notification::make()
                        ->title($matches ? 'Zone matches!' : 'Zone does not match')
                        ->success()
                        ->send();
                }),
        ]);
}
```

> **info**
> `Filament\Tables\Actions\Action`, `->actions([...])`, and `->form([...])` are all v3
> APIs. In v5 use `Filament\Actions\Action`, `->recordActions([...])`, and
> `->schema([...])`. The v3 names still exist as `@deprecated` aliases and will emit
> deprecation notices.

## Custom Widgets

### Create New Widget

```php
namespace App\Filament\Widgets;

use AIArmada\Tax\Models\TaxRate;
use Filament\Widgets\ChartWidget;

class TaxRatesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Tax Rates by Zone';
    
    protected function getData(): array
    {
        $zones = TaxZone::with('rates')->get();
        
        return [
            'datasets' => [
                [
                    'label' => 'Number of Rates',
                    'data' => $zones->pluck('rates')->map->count()->toArray(),
                    'backgroundColor' => ['#f00', '#0f0', '#00f', '#ff0', '#f0f'],
                ],
            ],
            'labels' => $zones->pluck('name')->toArray(),
        ];
    }
    
    protected function getType(): string
    {
        return 'bar';
    }
}
```

### Replace Built-in Widget

`FilamentTaxPlugin` has a `widgets(bool)` toggle. Turn the built-ins off and register
your own instead of trying to extend the `final` package widgets:

```php
// Admin panel provider
use AIArmada\FilamentTax\FilamentTaxPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FilamentTaxPlugin::make()->widgets(false),
        ])
        ->widgets([
            App\Filament\Widgets\TaxRatesChartWidget::class,
            App\Filament\Widgets\CustomTaxStatsWidget::class,
        ]);
}
```

## Relation Managers

### Add to Existing Resource

Resources are `final`, so add the relation manager to your own replacement resource
registered in place of the built-in one (see [Replace Resource Classes](#replace-resource-classes)):

```php
namespace App\Filament\Resources;

use App\Filament\Resources\TaxZoneResource\RelationManagers\AuditLogsRelationManager;
use Filament\Resources\Resource;

class TaxZoneResource extends Resource
{
    public static function getRelations(): array
    {
        return [
            AuditLogsRelationManager::class,
        ];
    }
}
```

### Create Custom Relation Manager

```php
namespace App\Filament\Resources\TaxZoneResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';
    
    protected static ?string $title = 'Audit Log';
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

> **info**
> `TaxZone` has no `activities` relation. Add one with the `LogsActivity` concern from
> `commerce-support` (or your own `HasActivities` implementation) before pointing a
> relation manager at it.

## Custom Pages

### Add to Resource

```php
namespace App\Filament\Resources\TaxZoneResource\Pages;

use Filament\Resources\Pages\Page;

class ZoneAnalytics extends Page
{
    protected static string $resource = TaxZoneResource::class;
    
    protected static string $view = 'filament.pages.zone-analytics';
    
    public TaxZone $record;
    
    public function mount(TaxZone $record): void
    {
        $this->record = $record;
    }
    
    public function getTitle(): string
    {
        return 'Analytics: ' . $this->record->name;
    }
}
```

Register in resource:

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListTaxZones::route('/'),
        'create' => Pages\CreateTaxZone::route('/create'),
        'edit' => Pages\EditTaxZone::route('/{record}/edit'),
        'view' => Pages\ViewTaxZone::route('/{record}'),
        'analytics' => Pages\ZoneAnalytics::route('/{record}/analytics'),
    ];
}
```

## Authorization Customization

### Custom Permission Names

Permission names are hardcoded in the package's policies
(`AIArmada\FilamentTax\Policies\*`) and in the resource tables. `filament-authz` has no
`resources.prefix` key, and its `pages`/`widgets`/`panels` prefixes do not apply to
permissions declared inside a policy. To rename an ability, override the policy in your
own `AuthServiceProvider`:

```php
use AIArmada\Tax\Models\TaxZone;

Gate::policy(TaxZone::class, App\Policies\TaxZonePolicy::class);
```

### Gate-based Authorization

The shipped policies already gate on `tax.zones.*` abilities, so defining those gates is
enough — you do not need to override the resource's `canViewAny()`:

```php
// AuthServiceProvider
Gate::define('tax.zones.view', fn ($user) => $user->hasAnyRole(['admin', 'accountant']));
Gate::define('tax.zones.create', fn ($user) => $user->hasRole('admin'));
Gate::define('tax.zones.update', fn ($user) => $user->hasRole('admin'));
Gate::define('tax.zones.delete', fn ($user) => $user->hasRole('admin'));
```

Resource-level overrides still work if you need them:

```php
// In resource
public static function canViewAny(): bool
{
    return Gate::allows('tax.zones.view');
}

public static function canCreate(): bool
{
    return Gate::allows('tax.zones.create');
}
```

### Enforced abilities

The package registers policies for every tax model, so these Gate abilities gate resources, pages, table actions, and direct record URLs. Each record ability also requires the record to sit inside the current owner scope:

- Zones: `tax.zones.view`, `tax.zones.create`, `tax.zones.update`, `tax.zones.delete`
- Rates: `tax.rates.view`, `tax.rates.create`, `tax.rates.update`, `tax.rates.delete`
- Classes: `tax.classes.view`, `tax.classes.create`, `tax.classes.update`, `tax.classes.delete`
- Exemptions: `tax.exemptions.view`, `tax.exemptions.create`, `tax.exemptions.update`, `tax.exemptions.delete`, `tax.exemptions.approve`, `tax.exemptions.reject`, `tax.exemptions.renew`, `tax.exemptions.download`
- Settings: `tax.settings.manage`

## Views Customization

### Publish Views

```bash
php artisan vendor:publish --tag=filament-tax-views
```

This creates views in `resources/views/vendor/filament-tax/`:

```
resources/views/vendor/filament-tax/
├── pages/
│   └── manage-tax-settings.blade.php
└── widgets/
    └── zone-coverage.blade.php
```

### Override Specific View

Create a view at the same path to override:

```blade
{{-- resources/views/vendor/filament-tax/widgets/zone-coverage.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Tax Zone Coverage">
        {{-- Your custom implementation --}}
        <div class="grid grid-cols-3 gap-4">
            @foreach ($this->getZones() as $zone)
                <x-filament::card>
                    <h3 class="font-bold text-lg">{{ $zone->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $zone->code }}</p>
                    
                    <div class="mt-4">
                        <span class="text-2xl font-bold">{{ $zone->rates->count() }}</span>
                        <span class="text-sm text-gray-500">rates</span>
                    </div>
                </x-filament::card>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

## Localization

### Publish Translations

```bash
php artisan vendor:publish --tag=filament-tax-translations
```

### Override Translations

Edit `lang/vendor/filament-tax/en/messages.php`:

```php
return [
    'resources' => [
        'zone' => [
            'label' => 'Tax Region',
            'plural' => 'Tax Regions',
            'navigation_label' => 'Regions',
        ],
        'rate' => [
            'label' => 'Tax Percentage',
            'plural' => 'Tax Percentages',
        ],
    ],
    'fields' => [
        'rate' => 'Percentage',
        'is_compound' => 'Stacking Tax',
    ],
];
```

### Add New Language

Create `lang/vendor/filament-tax/ms/messages.php`:

```php
return [
    'resources' => [
        'zone' => [
            'label' => 'Zon Cukai',
            'plural' => 'Zon Cukai',
        ],
        'rate' => [
            'label' => 'Kadar Cukai',
            'plural' => 'Kadar Cukai',
        ],
    ],
];
```

## Multi-Tenancy

### Owner Scoping

The plugin respects the base tax package's owner scoping:

```php
// config/tax.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

### Per-Panel Tenant Context

`FilamentTaxPlugin` exposes only `zones()`, `classes()`, `rates()`, `exemptions()`,
`widgets()`, and `settingsPage()`. There is no `modifyResourceQuery()` hook. For
per-panel tenant scoping, register your own resource (see
[Replace Resource Classes](#replace-resource-classes)) and return an owner-scoped
query:

```php
public static function getEloquentQuery(): Builder
{
    $query = parent::getEloquentQuery();

    if (filament()->getCurrentPanel()?->getId() === 'store') {
        $query->forOwner(filament()->getTenant());
    }

    return $query;
}
```

> **info**
> `filament()->getTenant()` returns `Model|null`, not `HasTenant`. Read the tenant ID
> from the model (`->getKey()`) or use `getTenantOwnershipRelationshipName()` when you
> need a relation name. A Filament tenant is not a security boundary — keep the
> `OwnerUiScope`/`forOwner()` boundary in place on every read and write.

## Event Hooks

### Listen to Resource Events

```php
// EventServiceProvider
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;

TaxZone::created(function (TaxZone $zone) {
    Log::info('Tax zone created', ['zone' => $zone->toArray()]);
    
    // Create a default rate for a new zone
    TaxRate::create([
        'zone_id' => $zone->id,
        'name' => 'Default Rate',
        'tax_class' => 'standard',
        'rate' => (int) round(app(\AIArmada\Tax\Settings\TaxSettings::class)->defaultTaxRate * 100),
        'is_active' => true,
    ]);
});
```

> **warning**
> `TaxRate::$rate` is an integer in **basis points**, while `TaxSettings::$defaultTaxRate`
> is a **percentage** (`6` = 6%). Multiply by 100 when bridging the two — never write
> `config('tax.defaults.default_tax_rate')`, which does not exist. The real
> `config/tax.php` keys are `tax.defaults.currency`, `tax.defaults.prices_include_tax`,
> `tax.defaults.calculate_tax_on_shipping`, and `tax.defaults.round_per_rate`.

### Filament Lifecycle Hooks

```php
// In resource page
protected function afterCreate(): void
{
    Notification::make()
        ->title('Tax zone created')
        ->success()
        ->send();
        
    activity()
        ->causedBy(auth()->user())
        ->performedOn($this->record)
        ->log('Created tax zone');
}

protected function afterSave(): void
{
    // Clear related caches
    Cache::tags(['tax'])->flush();
}
```
