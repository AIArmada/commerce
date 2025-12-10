# Order Resource

> **Document:** 02 of 04  
> **Package:** `aiarmada/filament-orders`  
> **Status:** Vision

---

## Overview

The OrderResource provides comprehensive order management including viewing, status transitions, and fulfillment tracking.

---

## Resource Structure

```php
namespace AIArmada\FilamentOrders\Resources;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    // Orders are primarily view-only (no create/edit traditional forms)
    public static function canCreate(): bool
    {
        return false;
    }
}
```

---

## Table Configuration

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('order_number')
                ->searchable()
                ->copyable(),
            TextColumn::make('customer.full_name')
                ->searchable()
                ->url(fn ($record) => CustomerResource::getUrl('view', ['record' => $record->customer])),
            TextColumn::make('grand_total')
                ->money('MYR')
                ->sortable(),
            TextColumn::make('status')
                ->badge()
                ->color(fn ($state) => $state->color())
                ->icon(fn ($state) => $state->icon()),
            TextColumn::make('items_count')
                ->counts('items')
                ->label('Items'),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([
            SelectFilter::make('status')
                ->options(OrderStatus::class)
                ->multiple(),
            Filter::make('created_at')
                ->form([
                    DatePicker::make('from'),
                    DatePicker::make('until'),
                ])
                ->query(fn ($query, $data) => $query
                    ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                    ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']))
                ),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('print')
                ->icon('heroicon-o-printer')
                ->url(fn ($record) => route('orders.invoice.pdf', $record))
                ->openUrlInNewTab(),
        ]);
}
```

---

## View Page (Order Detail)

```php
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            OrderStatusWidget::class,
            OrderTimelineWidget::class,
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // Order header
            Section::make()->schema([
                Grid::make(4)->schema([
                    TextEntry::make('order_number')
                        ->label('Order')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => $state->color()),
                    TextEntry::make('created_at')
                        ->dateTime(),
                    TextEntry::make('grand_total')
                        ->money('MYR')
                        ->weight(FontWeight::Bold),
                ]),
            ]),

            // Customer & Addresses
            Grid::make(2)->schema([
                Section::make('Customer')->schema([
                    TextEntry::make('customer.full_name'),
                    TextEntry::make('customer.email'),
                    TextEntry::make('customer.phone'),
                ]),
                Grid::make(2)->schema([
                    Section::make('Billing Address')->schema([
                        TextEntry::make('billingAddress.formatted_address')
                            ->html(),
                    ]),
                    Section::make('Shipping Address')->schema([
                        TextEntry::make('shippingAddress.formatted_address')
                            ->html(),
                    ]),
                ]),
            ]),

            // Order items
            Section::make('Items')->schema([
                RepeatableEntry::make('items')->schema([
                    TextEntry::make('name'),
                    TextEntry::make('sku'),
                    TextEntry::make('quantity'),
                    TextEntry::make('unit_price')->money('MYR'),
                    TextEntry::make('line_total')->money('MYR'),
                ])->columns(5),
            ]),

            // Totals
            Section::make('Totals')->schema([
                Grid::make(2)->schema([
                    TextEntry::make('subtotal')->money('MYR'),
                    TextEntry::make('discount_total')->money('MYR'),
                    TextEntry::make('shipping_total')->money('MYR'),
                    TextEntry::make('tax_total')->money('MYR'),
                    TextEntry::make('grand_total')
                        ->money('MYR')
                        ->weight(FontWeight::Bold),
                ]),
            ]),
        ]);
    }
}
```

---

## Status Transition Actions

```php
protected function getHeaderActions(): array
{
    return [
        // Process order (move to processing)
        Action::make('process')
            ->label('Mark as Processing')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('info')
            ->visible(fn () => $this->record->status->canTransitionTo(Processing::class))
            ->requiresConfirmation()
            ->action(fn () => $this->record->status->transitionTo(Processing::class)),

        // Ship order
        Action::make('ship')
            ->label('Create Shipment')
            ->icon('heroicon-o-truck')
            ->visible(fn () => $this->record->status->canTransitionTo(Shipped::class))
            ->form([
                Select::make('carrier')
                    ->options(Carrier::pluck('name', 'code'))
                    ->required(),
                TextInput::make('tracking_number')
                    ->required(),
            ])
            ->action(function (array $data) {
                $this->record->status->transitionTo(Shipped::class, $data);
            }),

        // Cancel order
        Action::make('cancel')
            ->label('Cancel Order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn () => $this->record->status->canCancel())
            ->requiresConfirmation()
            ->form([
                Textarea::make('reason')->required(),
            ])
            ->action(function (array $data) {
                $this->record->status->transitionTo(Canceled::class, $data);
            }),

        // Refund
        Action::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn () => $this->record->isPaid())
            ->form([
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('RM')
                    ->default(fn () => $this->record->getRefundableAmount()->getAmount() / 100),
                Textarea::make('reason')->required(),
            ])
            ->action(function (array $data) {
                app(RefundProcessor::class)->refund($this->record, $data['amount'] * 100, $data['reason']);
            }),
    ];
}
```

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-fulfillment-queue.md](03-fulfillment-queue.md)
