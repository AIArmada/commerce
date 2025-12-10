# Customer Resource

> **Document:** 02 of 03  
> **Package:** `aiarmada/filament-customers`  
> **Status:** Vision

---

## Overview

The CustomerResource provides a 360-degree customer view with order history, addresses, and segment information.

---

## Resource Structure

```php
namespace AIArmada\FilamentCustomers\Resources;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'CRM';
    protected static ?int $navigationSort = 1;
}
```

---

## Form Schema

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Contact Information')->schema([
            TextInput::make('first_name')->required(),
            TextInput::make('last_name'),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('phone')->tel(),
        ])->columns(2),

        Section::make('Business Information')->schema([
            TextInput::make('company'),
            TextInput::make('tax_number')
                ->label('SST/VAT Number'),
        ])->columns(2),

        Section::make('Preferences')->schema([
            Toggle::make('accepts_marketing')
                ->label('Accepts Marketing Emails'),
            Select::make('locale')
                ->options(['en' => 'English', 'ms' => 'Bahasa Malaysia']),
            Select::make('currency')
                ->options(['MYR' => 'MYR', 'USD' => 'USD', 'SGD' => 'SGD']),
        ])->columns(3),

        Section::make('Segments & Groups')->schema([
            Select::make('segments')
                ->relationship('segments', 'name')
                ->multiple()
                ->preload(),
            Select::make('groups')
                ->relationship('groups', 'name')
                ->multiple()
                ->preload(),
        ])->columns(2),

        Section::make('Notes')->schema([
            Textarea::make('notes'),
        ]),
    ]);
}
```

---

## Table Configuration

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('full_name')
                ->searchable(['first_name', 'last_name'])
                ->sortable(),
            TextColumn::make('email')
                ->searchable()
                ->copyable(),
            TextColumn::make('orders_count')
                ->label('Orders')
                ->sortable(),
            TextColumn::make('total_spent')
                ->money('MYR')
                ->sortable(),
            TextColumn::make('last_order_at')
                ->label('Last Order')
                ->since()
                ->sortable(),
            TextColumn::make('segments.name')
                ->badge()
                ->separator(','),
            IconColumn::make('accepts_marketing')
                ->boolean(),
        ])
        ->filters([
            SelectFilter::make('segments')
                ->relationship('segments', 'name')
                ->multiple(),
            SelectFilter::make('groups')
                ->relationship('groups', 'name'),
            Filter::make('high_value')
                ->label('High Value (> RM 1000)')
                ->query(fn ($query) => $query->where('total_spent', '>=', 100000)),
            Filter::make('at_risk')
                ->label('At Risk (No order 90+ days)')
                ->query(fn ($query) => $query
                    ->where('last_order_at', '<', now()->subDays(90))
                    ->whereNotNull('last_order_at')),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
        ]);
}
```

---

## View Page (Customer 360)

```php
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerStatsWidget::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // Stats header
            Grid::make(4)->schema([
                TextEntry::make('orders_count')
                    ->label('Orders')
                    ->weight(FontWeight::Bold),
                TextEntry::make('total_spent')
                    ->label('Lifetime Value')
                    ->money('MYR')
                    ->weight(FontWeight::Bold),
                TextEntry::make('average_order_value')
                    ->label('Avg Order')
                    ->formatStateUsing(fn ($record) => 
                        money($record->orders_count > 0 
                            ? $record->total_spent / $record->orders_count 
                            : 0)
                    ),
                TextEntry::make('last_order_at')
                    ->label('Last Order')
                    ->since(),
            ]),

            // Segments
            Section::make()->schema([
                TextEntry::make('segments.name')
                    ->label('Segments')
                    ->badge(),
            ]),

            // Tabs for detailed info
            Tabs::make('Customer Details')->tabs([
                Tab::make('Orders')->schema([
                    RepeatableEntry::make('orders')
                        ->schema([
                            TextEntry::make('order_number')
                                ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
                            TextEntry::make('grand_total')->money('MYR'),
                            TextEntry::make('status')->badge(),
                            TextEntry::make('created_at')->dateTime(),
                        ])->columns(4),
                ]),
                Tab::make('Addresses')->schema([
                    RepeatableEntry::make('addresses')
                        ->schema([
                            TextEntry::make('label'),
                            TextEntry::make('formatted_address'),
                            IconEntry::make('is_default_billing')->boolean(),
                            IconEntry::make('is_default_shipping')->boolean(),
                        ])->columns(4),
                ]),
                Tab::make('Wishlist')->schema([
                    // Wishlist items
                ]),
                Tab::make('Activity')->schema([
                    // Activity timeline
                ]),
            ]),
        ]);
    }
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-dashboard-widgets.md](03-dashboard-widgets.md)
