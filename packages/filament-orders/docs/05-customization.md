---
title: Customization
---

# Customization

## Extending the Order Resource

`AIArmada\FilamentOrders\Resources\OrderResource` is `final`, so extend the
helper classes it delegates to instead: `OrderForm::schema()`,
`OrdersTable::configure()` and `OrderInfolist::schema()`. Register your own
resource through `$panel->resources([...])` and point your pages at it with
`protected static string $resource`.

### Custom Columns

```php
namespace App\Filament\Resources\OrderResource\Tables;

use AIArmada\FilamentOrders\Resources\OrderResource\Tables\OrdersTable as BaseOrdersTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable extends BaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->columns([
                ...parent::configure($table)->getColumns(),

                // Add custom column
                TextColumn::make('customer_data.name')
                    ->label('Customer')
                    ->searchable(),
            ]);
    }
}
```

### Custom Filters

```php
public static function configure(Table $table): Table
{
    return parent::configure($table)
        ->filters([
            ...parent::configure($table)->getFilters(),

            // Add custom filter
            Tables\Filters\SelectFilter::make('status')
                ->options(\AIArmada\FilamentOrders\Resources\OrderResource::getStatusOptions()),
        ]);
}
```

### Custom Actions

Resource pages are not `final`, so extend the page and add header actions:

```php
namespace App\Filament\Resources\OrderResource\Pages;

use AIArmada\FilamentOrders\Resources\OrderResource\Pages\EditOrder as BaseEditOrder;
use AIArmada\Orders\Models\Order;
use Filament\Actions\Action;

class EditOrder extends BaseEditOrder
{
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),

            Action::make('custom_action')
                ->label('Custom Action')
                ->icon('heroicon-o-star')
                ->action(fn (Order $record) => $this->handleCustomAction($record)),
        ];
    }
}
```

## Custom Widgets

### Creating a Custom Widget

```php
namespace App\Filament\Widgets;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Orders\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Monthly Revenue';

    protected function getData(): array
    {
        // grand_total is stored in minor units; format through the shared
        // MoneyFormatter so the scale and ISO 4217 code are never ambiguous.
        $rows = Order::query()
            ->forOwner()
            ->whereNotNull('paid_at')
            ->selectRaw('currency')
            ->selectRaw('SUM(grand_total) as total')
            ->groupBy('currency')
            ->get();

        $byCurrency = $rows->mapWithKeys(fn (Order $row): array => [
            $row->currency => MoneyFormatter::formatMinorWithCode((int) $row->total, $row->currency),
        ]);

        return [
            'datasets' => [
                [
                    'label' => 'Paid revenue',
                    'data' => $byCurrency->values()->all(),
                ],
            ],
            'labels' => $byCurrency->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

### Registering Custom Widgets

In your panel provider:

```php
use App\Filament\Widgets\OrderRevenueChart;

public function panel(Panel $panel): Panel
{
    return $panel
        ->widgets([
            OrderRevenueChart::class,
        ]);
}
```

## Custom Pages

The package provides dedicated pages for fulfillment and timeline workflows:

- `OrderFulfillmentPage` — manage order fulfillment operations.
- `OrderTimelinePage` — full order event timeline.

Extend these pages to override actions, fields, or layout:

```php
namespace App\Filament\Pages;

use AIArmada\FilamentOrders\Pages\OrderFulfillmentPage as BaseFulfillmentPage;

class OrderFulfillmentPage extends BaseFulfillmentPage
{
    // ...
}
```

## Custom Views

### Timeline Widget

Publish and customize the timeline view:

```bash
php artisan vendor:publish --tag=filament-orders-views
```

Edit `resources/views/vendor/filament-orders/widgets/order-timeline.blade.php`.

## Adding Custom Payment Gateways

Update config to add your gateways:

```php
// config/filament-orders.php
'payment_gateways' => [
    'stripe' => 'Stripe',
    'chip' => 'CHIP',
    'manual' => 'Manual',
    // Add custom gateways
    'billplz' => 'Billplz',
    'ipay88' => 'iPay88',
    'senangpay' => 'SenangPay',
],
```

## Custom Order Form Schema

`OrderForm` exposes a static `schema(): array`, so append to that array from your
own resource's `form(Schema $schema)`:

```php
namespace App\Filament\Resources\OrderResource\Schemas;

use AIArmada\FilamentOrders\Resources\OrderResource\Schemas\OrderForm as BaseOrderForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    /**
     * @return array<int, Section>
     */
    public static function schema(): array
    {
        return [
            ...BaseOrderForm::schema(),

            Section::make('Sales')
                ->schema([
                    Select::make('sales_rep_id')
                        ->relationship('salesRep', 'name')
                        ->label('Sales Representative'),
                ]),
        ];
    }
}
```

## Disabling Features

### Disable Invoice Downloads

```php
// config/filament-orders.php
'features' => [
    'enable_invoice_download' => false,
],
```

Or conditionally in the view page:

```php
Actions\Action::make('download_invoice')
    ->visible(fn () => config('filament-orders.features.enable_invoice_download', true)),
```

## Custom Authorization

`AIArmada\Orders\Policies\OrderPolicy` is `final`, so write your own policy
against the same ability names and register it in a service provider
(`AuthServiceProvider::$policies` no longer exists in Laravel 11+):

```php
namespace App\Policies;

use AIArmada\Orders\Models\Order;

class OrderPolicy
{
    public function cancel(User $user, Order $order): bool
    {
        // Custom logic
        if ($order->grand_total > 100000) {
            return $user->hasRole('supervisor');
        }

        return $user->can('cancel', $order); // delegates to the package policy
    }
}
```

Register your policy:

```php
// AppServiceProvider::boot()
use AIArmada\Orders\Models\Order;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Order::class, \App\Policies\OrderPolicy::class);
}
```
