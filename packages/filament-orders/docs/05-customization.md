---
title: Customization
---

# Customization

## Extending the Order Resource

### Custom Columns

Create a custom resource that extends the base:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentOrders\Resources\OrderResource as BaseOrderResource;

class OrderResource extends BaseOrderResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::table($table)->getColumns(),
                
                // Add custom column
                Tables\Columns\TextColumn::make('custom_field')
                    ->label('Custom Field'),
            ]);
    }
}
```

### Custom Filters

```php
public static function table(Table $table): Table
{
    return parent::table($table)
        ->filters([
            ...parent::table($table)->getFilters(),
            
            // Add custom filter
            Tables\Filters\SelectFilter::make('priority')
                ->options([
                    'high' => 'High Priority',
                    'normal' => 'Normal',
                ]),
        ]);
}
```

### Custom Actions

```php
protected function getHeaderActions(): array
{
    return [
        ...parent::getHeaderActions(),
        
        Actions\Action::make('custom_action')
            ->label('Custom Action')
            ->icon('heroicon-o-star')
            ->action(fn (Order $record) => $this->handleCustomAction($record)),
    ];
}
```

## Custom Widgets

### Creating a Custom Widget

```php
namespace App\Filament\Widgets;

use AIArmada\Orders\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderRevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Monthly Revenue';

    protected function getData(): array
    {
        $data = Order::query()
            ->forOwner()
            ->whereNotNull('paid_at')
            ->selectRaw('MONTH(created_at) as month, SUM(grand_total) as total')
            ->groupBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('total')->toArray(),
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
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

Override the form in your custom resource:

```php
public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Order Details')
                ->schema([
                    Forms\Components\TextInput::make('order_number')
                        ->disabled(),
                        
                    // Add custom fields
                    Forms\Components\Select::make('sales_rep_id')
                        ->relationship('salesRep', 'name')
                        ->label('Sales Representative'),
                ]),
        ]);
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

Override the policy for custom permission logic:

```php
namespace App\Policies;

use AIArmada\Orders\Policies\OrderPolicy as BaseOrderPolicy;

class OrderPolicy extends BaseOrderPolicy
{
    public function cancel(User $user, Order $order): bool
    {
        // Custom logic
        if ($order->grand_total > 100000) {
            return $user->hasRole('supervisor');
        }
        
        return parent::cancel($user, $order);
    }
}
```

Register your policy:

```php
// AuthServiceProvider.php
protected $policies = [
    Order::class => OrderPolicy::class,
];
```
