# Filament Cashier Chip Vision: Subscription Management

> **Document:** 02 of 05  
> **Package:** `aiarmada/filament-cashier-chip`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

The Subscription Management module provides comprehensive Filament admin interface for managing all subscription lifecycle operations. This is the core feature of `filament-cashier-chip`, enabling admins to view, create, modify, and cancel subscriptions without writing code.

---

## SubscriptionResource

### Table View

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ Subscriptions                                                    [+ Create]     │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [Search...]                          [Status ▾] [Plan ▾] [Date Range ▾]         │
├──────┬────────────────┬──────────────┬──────────┬───────────────┬───────────────┤
│ User │ Plan           │ Status       │ Amount   │ Next Billing  │ Actions       │
├──────┼────────────────┼──────────────┼──────────┼───────────────┼───────────────┤
│ John │ Premium Annual │ 🟢 Active    │ RM 299   │ Dec 15, 2025  │ [View] [⋮]    │
│ Jane │ Basic Monthly  │ 🟡 On Trial  │ RM 29    │ Trial ends    │ [View] [⋮]    │
│ Bob  │ Pro Monthly    │ 🔴 Canceled  │ RM 99    │ Grace period  │ [View] [⋮]    │
└──────┴────────────────┴──────────────┴──────────┴───────────────┴───────────────┘
│ [Bulk: Cancel] [Export CSV]                              Showing 1-25 of 142    │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| User | Relationship | Billable model name/email |
| Name | Text | Subscription type (default, premium, etc.) |
| Plan ID | Text | Linked plan/price identifier |
| Status | Badge | Active, Trialing, Canceled, Past Due, Paused |
| Amount | Money | Recurring amount from items |
| Quantity | Number | Total quantity across items |
| Trial Ends At | Date | Trial expiration (if applicable) |
| Ends At | Date | Cancellation effective date |
| Next Billing | Date | Calculated next charge date |
| Created At | Date | Subscription creation date |

### Status Badge Colors

```php
public static function getStatusColor(Subscription $subscription): string
{
    return match (true) {
        $subscription->onTrial() => 'warning',      // 🟡 Yellow
        $subscription->active() => 'success',       // 🟢 Green
        $subscription->onGracePeriod() => 'info',   // 🔵 Blue
        $subscription->canceled() => 'danger',      // 🔴 Red
        $subscription->pastDue() => 'danger',       // 🔴 Red
        $subscription->paused() => 'gray',          // ⚪ Gray
        default => 'gray',
    };
}
```

---

## Subscription Infolist (View Page)

### Overview Section

```
┌─────────────────────────────────────────────────────────────────┐
│ Premium Annual Subscription                                     │
├─────────────────────────────────────────────────────────────────┤
│ Status: 🟢 Active                                               │
│                                                                 │
│ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐     │
│ │ Monthly Amount  │ │ Total Quantity  │ │ Billing Cycle   │     │
│ │ RM 299.00       │ │ 1               │ │ Yearly          │     │
│ └─────────────────┘ └─────────────────┘ └─────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
```

### Billing Details Section

```
┌─────────────────────────────────────────────────────────────────┐
│ Billing Details                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Customer:          John Doe (john@example.com)                  │
│ Plan:              price_premium_annual                         │
│ Created:           November 15, 2024                            │
│ Current Period:    Dec 15, 2024 → Dec 15, 2025                  │
│ Next Billing:      December 15, 2025                            │
│ Payment Method:    Visa •••• 4242                               │
└─────────────────────────────────────────────────────────────────┘
```

### Trial Information (Conditional)

```
┌─────────────────────────────────────────────────────────────────┐
│ Trial Period                                                    │
├─────────────────────────────────────────────────────────────────┤
│ Trial Status:      🟡 Active                                    │
│ Trial Started:     December 1, 2025                             │
│ Trial Ends:        December 15, 2025 (14 days remaining)        │
│                                                                 │
│ [Extend Trial] [End Trial Now]                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Grace Period (Conditional)

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚠️ Subscription Canceled                                        │
├─────────────────────────────────────────────────────────────────┤
│ Canceled On:       December 5, 2025                             │
│ Access Until:      December 15, 2025 (10 days remaining)        │
│                                                                 │
│ [Resume Subscription]                                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## Subscription Actions

### Header Actions

| Action | Icon | Condition | Description |
|--------|------|-----------|-------------|
| Cancel | ❌ | `active()` | Cancel at period end |
| Cancel Now | 🗑️ | `active()` | Immediate cancellation |
| Resume | ▶️ | `onGracePeriod()` | Resume canceled subscription |
| Pause | ⏸️ | `active()` | Pause billing (if supported) |
| Swap Plan | 🔄 | `active()` | Change to different plan |
| Update Quantity | 🔢 | `active()` | Adjust seat count |
| Extend Trial | ➕ | `onTrial()` | Add trial days |
| End Trial | ⏹️ | `onTrial()` | Convert to paid immediately |

### Cancel Action Modal

```php
Action::make('cancel')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->visible(fn (Subscription $record) => $record->active())
    ->requiresConfirmation()
    ->modalHeading('Cancel Subscription')
    ->modalDescription('The customer will retain access until the end of their current billing period.')
    ->modalSubmitActionLabel('Cancel Subscription')
    ->action(fn (Subscription $record) => $record->cancel())
    ->successNotificationTitle('Subscription canceled');
```

### Swap Plan Action Modal

```php
Action::make('swap')
    ->icon('heroicon-o-arrows-right-left')
    ->form([
        Select::make('plan')
            ->label('New Plan')
            ->options([
                'price_basic_monthly' => 'Basic Monthly - RM 29/mo',
                'price_pro_monthly' => 'Pro Monthly - RM 99/mo',
                'price_premium_annual' => 'Premium Annual - RM 299/yr',
            ])
            ->required(),
        Toggle::make('prorate')
            ->label('Prorate charges')
            ->default(true),
    ])
    ->action(function (Subscription $record, array $data) {
        if ($data['prorate']) {
            $record->swap($data['plan']);
        } else {
            $record->noProrate()->swap($data['plan']);
        }
    });
```

### Extend Trial Action

```php
Action::make('extendTrial')
    ->icon('heroicon-o-plus-circle')
    ->visible(fn (Subscription $record) => $record->onTrial())
    ->form([
        TextInput::make('days')
            ->label('Additional Trial Days')
            ->numeric()
            ->minValue(1)
            ->maxValue(90)
            ->default(7)
            ->required(),
    ])
    ->action(function (Subscription $record, array $data) {
        $newEndDate = $record->trial_ends_at->addDays($data['days']);
        $record->extendTrial($newEndDate);
    });
```

---

## Create Subscription

### Form Schema

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Customer')
            ->schema([
                Select::make('user_id')
                    ->label('Customer')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
            ]),

        Section::make('Plan Selection')
            ->schema([
                TextInput::make('type')
                    ->label('Subscription Name')
                    ->default('default')
                    ->required(),
                    
                Select::make('plan_id')
                    ->label('Plan')
                    ->options(static::getAvailablePlans())
                    ->required(),
                    
                TextInput::make('quantity')
                    ->label('Quantity (Seats)')
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ]),

        Section::make('Trial Period')
            ->schema([
                Toggle::make('has_trial')
                    ->label('Include Trial Period')
                    ->live(),
                    
                TextInput::make('trial_days')
                    ->label('Trial Days')
                    ->numeric()
                    ->default(14)
                    ->visible(fn (Get $get) => $get('has_trial')),
            ]),

        Section::make('Payment')
            ->schema([
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options(fn (Get $get) => static::getPaymentMethods($get('user_id')))
                    ->placeholder('Use default payment method'),
            ]),
    ]);
}
```

### Create Action Handler

```php
public function create(): void
{
    $data = $this->form->getState();

    $user = User::find($data['user_id']);
    
    $builder = $user->newSubscription($data['type'], $data['plan_id']);
    
    if ($data['quantity'] > 1) {
        $builder->quantity($data['quantity']);
    }
    
    if ($data['has_trial']) {
        $builder->trialDays($data['trial_days']);
    }
    
    if ($data['payment_method']) {
        $builder->create($data['payment_method']);
    } else {
        $builder->create();
    }
    
    Notification::make()
        ->title('Subscription created')
        ->success()
        ->send();
}
```

---

## Filters

### Status Filter

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'trialing' => 'On Trial',
        'canceled' => 'Canceled',
        'past_due' => 'Past Due',
        'paused' => 'Paused',
    ])
    ->query(function (Builder $query, array $data) {
        return match ($data['value']) {
            'active' => $query->whereNull('ends_at')->whereNull('trial_ends_at'),
            'trialing' => $query->whereNotNull('trial_ends_at')
                                ->where('trial_ends_at', '>', now()),
            'canceled' => $query->whereNotNull('ends_at'),
            'past_due' => $query->where('chip_status', 'past_due'),
            'paused' => $query->where('chip_status', 'paused'),
            default => $query,
        };
    });
```

### Tabs

```php
public function getTabs(): array
{
    return [
        'all' => Tab::make('All'),
        'active' => Tab::make('Active')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->whereNull('ends_at')->whereNull('trial_ends_at')
            )
            ->badge(fn () => Subscription::active()->count()),
        'trialing' => Tab::make('Trialing')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->onTrial()
            )
            ->badge(fn () => Subscription::onTrial()->count()),
        'canceled' => Tab::make('Canceled')
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->canceled()
            )
            ->badge(fn () => Subscription::canceled()->count()),
    ];
}
```

---

## Bulk Actions

### Bulk Cancel

```php
BulkAction::make('cancel')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalDescription('This will cancel all selected active subscriptions at the end of their billing periods.')
    ->deselectRecordsAfterCompletion()
    ->action(function (Collection $records) {
        $records->each(function (Subscription $subscription) {
            if ($subscription->active()) {
                $subscription->cancel();
            }
        });
    });
```

### Export Subscriptions

```php
BulkAction::make('export')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function (Collection $records) {
        return Excel::download(
            new SubscriptionsExport($records),
            'subscriptions-' . now()->format('Y-m-d') . '.csv'
        );
    });
```

---

## SubscriptionItemRelationManager

### Table Schema

```php
public function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('price_id')
                ->label('Price ID'),
            TextColumn::make('quantity')
                ->label('Quantity'),
            TextColumn::make('created_at')
                ->label('Added')
                ->since(),
        ])
        ->actions([
            Action::make('updateQuantity')
                ->icon('heroicon-o-pencil')
                ->form([
                    TextInput::make('quantity')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->action(fn (SubscriptionItem $record, array $data) =>
                    $record->updateQuantity($data['quantity'])
                ),
        ]);
}
```

---

## Webhooks Integration

The resource automatically reflects subscription state changes from webhooks:

| Webhook Event | UI Update |
|---------------|-----------|
| `subscription.created` | New row appears |
| `subscription.updated` | Status/amounts update |
| `subscription.deleted` | Shows as ended |
| `invoice.payment_failed` | Status: Past Due |
| `invoice.paid` | Status: Active |

---

## Implementation Checklist

### Phase 1: Core Resource
- [ ] `SubscriptionResource` with table and columns
- [ ] Status badge component
- [ ] Basic filters (status, date)

### Phase 2: View Page
- [ ] Infolist with all subscription details
- [ ] Trial/grace period sections
- [ ] Header actions (cancel, resume)

### Phase 3: Actions
- [ ] Swap plan action with form
- [ ] Extend trial action
- [ ] Update quantity action
- [ ] Cancel now action

### Phase 4: Create Form
- [ ] Customer selection
- [ ] Plan selection
- [ ] Trial configuration
- [ ] Payment method selection

### Phase 5: Advanced Features
- [ ] Bulk actions
- [ ] CSV export
- [ ] Subscription items relation manager
- [ ] Activity log integration

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-customer-portal.md](03-customer-portal.md)
