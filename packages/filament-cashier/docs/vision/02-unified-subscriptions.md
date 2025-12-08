# Filament Cashier Vision: Unified Subscriptions

> **Document:** 02 of 05  
> **Package:** `aiarmada/filament-cashier`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

The Unified Subscription Resource provides a single Filament admin interface for managing subscriptions across ALL payment gateways. Instead of switching between Stripe admin and CHIP admin, operators see all subscriptions in one table with gateway-aware actions.

---

## UnifiedSubscriptionResource

### Table View

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│ All Subscriptions                                              [+ Create]               │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ [Search...]                    [Gateway ▾] [Status ▾] [Plan ▾] [Date Range ▾]           │
├──────┬──────────┬────────────────┬──────────────┬──────────┬───────────────┬────────────┤
│ User │ Gateway  │ Plan           │ Status       │ Amount   │ Next Billing  │ Actions    │
├──────┼──────────┼────────────────┼──────────────┼──────────┼───────────────┼────────────┤
│ John │ 💳 Stripe│ Premium Annual │ 🟢 Active    │ $299     │ Dec 15, 2025  │ [View] [⋮] │
│ Jane │ 🔷 CHIP  │ Basic Monthly  │ 🟡 On Trial  │ RM 29    │ Trial ends    │ [View] [⋮] │
│ Bob  │ 💳 Stripe│ Pro Monthly    │ 🔴 Canceled  │ $99      │ Grace period  │ [View] [⋮] │
│ Sara │ 🔷 CHIP  │ Enterprise     │ 🟢 Active    │ RM 999   │ Jan 1, 2026   │ [View] [⋮] │
└──────┴──────────┴────────────────┴──────────────┴──────────┴───────────────┴────────────┘
│ [Bulk: Cancel] [Export CSV]                                   Showing 1-25 of 842       │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

### Table Columns

| Column | Type | Description |
|--------|------|-------------|
| User | Relationship | Billable model name/email |
| Gateway | Badge | Stripe (💳) or CHIP (🔷) with color-coded icon |
| Name | Text | Subscription type (default, premium, etc.) |
| Plan ID | Text | Price/plan identifier |
| Status | Badge | Normalized status across gateways |
| Amount | Money | Recurring amount (currency-aware) |
| Quantity | Number | Total quantity |
| Trial Ends At | Date | Trial expiration (if applicable) |
| Ends At | Date | Cancellation effective date |
| Next Billing | Date | Calculated next charge date |
| Created At | Date | Subscription creation date |

### Gateway Badge Component

```php
class GatewayBadge extends Component
{
    public function render(): View
    {
        return view('filament-cashier::components.gateway-badge', [
            'gateway' => $this->gateway,
            'config' => [
                'stripe' => [
                    'icon' => 'heroicon-o-credit-card',
                    'color' => 'indigo',
                    'label' => 'Stripe',
                ],
                'chip' => [
                    'icon' => 'heroicon-o-cube',
                    'color' => 'emerald',
                    'label' => 'CHIP',
                ],
            ],
        ]);
    }
}
```

---

## Data Source Architecture

### Unified Query Approach

```php
class UnifiedSubscriptionResource extends Resource
{
    protected static function getEloquentQuery(): Builder
    {
        // This resource doesn't use Eloquent directly
        // Instead, it uses a virtual table via the Cashier gateway manager
        throw new \LogicException('Use getRecords() instead');
    }
    
    public static function getRecords(): Collection
    {
        $user = auth()->user();
        
        // Aggregates from all installed gateways
        return Cashier::manager()
            ->gateways()
            ->flatMap(fn ($gateway) => $gateway->allSubscriptions())
            ->sortByDesc('created_at');
    }
}
```

### Unified Subscription DTO

```php
class UnifiedSubscription
{
    public function __construct(
        public string $id,
        public string $gateway,
        public string $userId,
        public string $type,
        public string $planId,
        public int $amount,
        public string $currency,
        public int $quantity,
        public SubscriptionStatus $status,
        public ?Carbon $trialEndsAt,
        public ?Carbon $endsAt,
        public Carbon $createdAt,
        public object $original, // Original Stripe/CHIP subscription object
    ) {}
    
    public static function fromStripe(StripeSubscription $sub): self
    {
        return new self(
            id: $sub->id,
            gateway: 'stripe',
            userId: $sub->user_id,
            type: $sub->type,
            planId: $sub->stripe_price,
            amount: $sub->items->first()?->price ?? 0,
            currency: 'USD',
            quantity: $sub->quantity,
            status: self::normalizeStripeStatus($sub),
            trialEndsAt: $sub->trial_ends_at,
            endsAt: $sub->ends_at,
            createdAt: $sub->created_at,
            original: $sub,
        );
    }
    
    public static function fromChip(ChipSubscription $sub): self
    {
        return new self(
            id: $sub->id,
            gateway: 'chip',
            userId: $sub->user_id,
            type: $sub->type,
            planId: $sub->plan_id,
            amount: $sub->items->sum('unit_amount'),
            currency: 'MYR',
            quantity: $sub->items->sum('quantity'),
            status: self::normalizeChipStatus($sub),
            trialEndsAt: $sub->trial_ends_at,
            endsAt: $sub->ends_at,
            createdAt: $sub->created_at,
            original: $sub,
        );
    }
}
```

### Status Normalization

```php
enum SubscriptionStatus: string
{
    case Active = 'active';
    case OnTrial = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case OnGracePeriod = 'grace_period';
    case Paused = 'paused';
    case Incomplete = 'incomplete';
    
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnTrial => 'warning',
            self::PastDue => 'danger',
            self::Canceled => 'danger',
            self::OnGracePeriod => 'info',
            self::Paused => 'gray',
            self::Incomplete => 'warning',
        };
    }
    
    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::OnTrial => 'heroicon-o-clock',
            self::PastDue => 'heroicon-o-exclamation-circle',
            self::Canceled => 'heroicon-o-x-circle',
            self::OnGracePeriod => 'heroicon-o-pause-circle',
            self::Paused => 'heroicon-o-pause',
            self::Incomplete => 'heroicon-o-question-mark-circle',
        };
    }
}
```

---

## Subscription Infolist (View Page)

### Overview Section

```
┌───────────────────────────────────────────────────────────────────┐
│ 💳 Stripe: Premium Annual Subscription                           │
├───────────────────────────────────────────────────────────────────┤
│ Status: 🟢 Active                      Gateway: 💳 Stripe         │
│                                                                   │
│ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐       │
│ │ Monthly Amount  │ │ Total Quantity  │ │ Billing Cycle   │       │
│ │ $299.00         │ │ 1               │ │ Yearly          │       │
│ └─────────────────┘ └─────────────────┘ └─────────────────┘       │
└───────────────────────────────────────────────────────────────────┘
```

### Gateway-Specific Details

```
┌───────────────────────────────────────────────────────────────────┐
│ Gateway Details (Stripe)                                          │
├───────────────────────────────────────────────────────────────────┤
│ Stripe Subscription ID:   sub_1234567890                          │
│ Stripe Customer ID:       cus_abcdefghij                          │
│ Stripe Price ID:          price_premium_annual                    │
│ Current Period:           Dec 15, 2024 → Dec 15, 2025             │
│ Collection Method:        charge_automatically                    │
│ Default Payment Method:   Visa •••• 4242                          │
│                                                                   │
│ [View in Stripe Dashboard ↗]                                      │
└───────────────────────────────────────────────────────────────────┘
```

OR for CHIP:

```
┌───────────────────────────────────────────────────────────────────┐
│ Gateway Details (CHIP)                                            │
├───────────────────────────────────────────────────────────────────┤
│ CHIP Subscription ID:     sub_chip_1234567890                     │
│ CHIP Customer ID:         cli_abcdefghij                          │
│ Schedule ID:              sched_recurring_123                     │
│ Payment Token:            tok_••••••4242                          │
│ Next Charge Date:         January 15, 2026                        │
│                                                                   │
│ [View in CHIP Dashboard ↗]                                        │
└───────────────────────────────────────────────────────────────────┘
```

---

## Gateway-Aware Actions

### Cancel Action

```php
Action::make('cancel')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->visible(fn (UnifiedSubscription $record) => $record->status === SubscriptionStatus::Active)
    ->requiresConfirmation()
    ->modalHeading(fn (UnifiedSubscription $record) => 
        "Cancel {$record->gateway} Subscription"
    )
    ->modalDescription(fn (UnifiedSubscription $record) => 
        "This will cancel the subscription on {$record->gateway}. " .
        "The customer will retain access until the end of their current billing period."
    )
    ->action(function (UnifiedSubscription $record) {
        // Delegate to the correct gateway
        match ($record->gateway) {
            'stripe' => $record->original->cancel(),
            'chip' => $record->original->cancel(),
        };
        
        Notification::make()
            ->title('Subscription canceled')
            ->success()
            ->send();
    });
```

### Resume Action

```php
Action::make('resume')
    ->icon('heroicon-o-play')
    ->visible(fn (UnifiedSubscription $record) => 
        $record->status === SubscriptionStatus::OnGracePeriod
    )
    ->action(function (UnifiedSubscription $record) {
        match ($record->gateway) {
            'stripe' => $record->original->resume(),
            'chip' => $record->original->resume(),
        };
    });
```

### Swap Plan Action (Gateway-Aware)

```php
Action::make('swap')
    ->icon('heroicon-o-arrows-right-left')
    ->form(fn (UnifiedSubscription $record) => [
        Select::make('plan')
            ->label('New Plan')
            ->options($this->getPlansForGateway($record->gateway))
            ->required(),
        Toggle::make('prorate')
            ->label('Prorate charges')
            ->default(true)
            ->visible(fn () => $record->gateway === 'stripe'),
    ])
    ->action(function (UnifiedSubscription $record, array $data) {
        match ($record->gateway) {
            'stripe' => $data['prorate'] 
                ? $record->original->swap($data['plan'])
                : $record->original->noProrate()->swap($data['plan']),
            'chip' => $record->original->swap($data['plan']),
        };
    });

protected function getPlansForGateway(string $gateway): array
{
    return match ($gateway) {
        'stripe' => config('cashier.stripe_plans', []),
        'chip' => config('cashier.chip_plans', []),
        default => [],
    };
}
```

---

## Create Subscription Form

### Gateway Selection Flow

```
┌───────────────────────────────────────────────────────────────────┐
│ Create Subscription                                               │
├───────────────────────────────────────────────────────────────────┤
│                                                                   │
│ Step 1: Select Customer                                           │
│ ┌─────────────────────────────────────────────────────────────┐   │
│ │ Customer: [Search customers...                           ▾] │   │
│ └─────────────────────────────────────────────────────────────┘   │
│                                                                   │
│ Step 2: Select Gateway                                            │
│ ┌─────────────────────────────────────────────────────────────┐   │
│ │ ○ 💳 Stripe    - Credit cards, ACH, international           │   │
│ │ ● 🔷 CHIP      - FPX, e-wallets, Malaysian payments          │   │
│ └─────────────────────────────────────────────────────────────┘   │
│                                                                   │
│ Step 3: Select Plan (CHIP Plans)                                  │
│ ┌─────────────────────────────────────────────────────────────┐   │
│ │ ○ Basic Monthly    - RM 29/mo                                │   │
│ │ ● Pro Monthly      - RM 99/mo                                │   │
│ │ ○ Premium Annual   - RM 899/yr                               │   │
│ └─────────────────────────────────────────────────────────────┘   │
│                                                                   │
│ Step 4: Payment Method                                            │
│ ┌─────────────────────────────────────────────────────────────┐   │
│ │ [Select from customer's CHIP payment methods...          ▾] │   │
│ │ Or: [Add new payment method]                                 │   │
│ └─────────────────────────────────────────────────────────────┘   │
│                                                                   │
│                                            [Cancel] [Create]      │
└───────────────────────────────────────────────────────────────────┘
```

### Form Implementation

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Wizard::make([
            Wizard\Step::make('Customer')
                ->schema([
                    Select::make('user_id')
                        ->label('Customer')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->required()
                        ->live(),
                ]),
                
            Wizard\Step::make('Gateway')
                ->schema([
                    Radio::make('gateway')
                        ->label('Payment Gateway')
                        ->options(fn () => static::getAvailableGateways())
                        ->descriptions([
                            'stripe' => 'Credit cards, ACH, international payments',
                            'chip' => 'FPX, e-wallets, Malaysian payments',
                        ])
                        ->required()
                        ->live(),
                ]),
                
            Wizard\Step::make('Plan')
                ->schema([
                    Radio::make('plan_id')
                        ->label('Select Plan')
                        ->options(fn (Get $get) => static::getPlansForGateway($get('gateway')))
                        ->required(),
                        
                    TextInput::make('quantity')
                        ->label('Quantity (Seats)')
                        ->numeric()
                        ->default(1)
                        ->minValue(1),
                        
                    Toggle::make('has_trial')
                        ->label('Include Trial Period')
                        ->live(),
                        
                    TextInput::make('trial_days')
                        ->label('Trial Days')
                        ->numeric()
                        ->default(14)
                        ->visible(fn (Get $get) => $get('has_trial')),
                ]),
                
            Wizard\Step::make('Payment')
                ->schema([
                    Select::make('payment_method')
                        ->label('Payment Method')
                        ->options(fn (Get $get) => static::getPaymentMethodsForGateway(
                            $get('user_id'),
                            $get('gateway')
                        ))
                        ->placeholder('Use default or add new'),
                ]),
        ]),
    ]);
}
```

### Create Handler with Gateway Delegation

```php
public function create(): void
{
    $data = $this->form->getState();
    
    $user = User::find($data['user_id']);
    $gateway = $data['gateway'];
    
    // Use the unified cashier API
    $builder = $user->newGatewaySubscription(
        type: 'default',
        price: $data['plan_id'],
        gateway: $gateway
    );
    
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
        ->body("Created on {$gateway}")
        ->success()
        ->send();
}
```

---

## Filters

### Gateway Filter

```php
SelectFilter::make('gateway')
    ->options([
        'stripe' => '💳 Stripe',
        'chip' => '🔷 CHIP',
    ])
    ->query(function (array $data) {
        // This filter works on the DTO collection, not Eloquent
        // Filtering is applied in getRecords()
    });
```

### Status Filter (Normalized)

```php
SelectFilter::make('status')
    ->options(
        collect(SubscriptionStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->name])
            ->toArray()
    );
```

### Tabs

```php
public function getTabs(): array
{
    $gateways = Cashier::manager()->availableGateways();
    
    return [
        'all' => Tab::make('All Subscriptions')
            ->badge(fn () => $this->getAllCount()),
            
        'stripe' => Tab::make('💳 Stripe')
            ->visible(fn () => $gateways->contains('stripe'))
            ->modifyQueryUsing(fn () => $this->filterByGateway('stripe'))
            ->badge(fn () => $this->getGatewayCount('stripe')),
            
        'chip' => Tab::make('🔷 CHIP')
            ->visible(fn () => $gateways->contains('chip'))
            ->modifyQueryUsing(fn () => $this->filterByGateway('chip'))
            ->badge(fn () => $this->getGatewayCount('chip')),
            
        'active' => Tab::make('Active')
            ->modifyQueryUsing(fn () => $this->filterByStatus(SubscriptionStatus::Active))
            ->badge(fn () => $this->getStatusCount(SubscriptionStatus::Active)),
            
        'issues' => Tab::make('⚠️ Needs Attention')
            ->modifyQueryUsing(fn () => $this->filterByIssues())
            ->badge(fn () => $this->getIssuesCount())
            ->badgeColor('danger'),
    ];
}
```

---

## Bulk Actions

### Bulk Cancel (Gateway-Aware)

```php
BulkAction::make('cancel')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalDescription(function (Collection $records) {
        $gateways = $records->groupBy('gateway');
        $summary = $gateways->map(fn ($items, $gateway) => 
            count($items) . ' on ' . ucfirst($gateway)
        )->join(', ');
        
        return "This will cancel: {$summary}";
    })
    ->action(function (Collection $records) {
        $records->each(fn (UnifiedSubscription $sub) => 
            $sub->original->cancel()
        );
    });
```

### Export with Gateway Column

```php
BulkAction::make('export')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function (Collection $records) {
        return Excel::download(
            new UnifiedSubscriptionsExport($records),
            'subscriptions-all-gateways-' . now()->format('Y-m-d') . '.csv'
        );
    });
```

---

## Implementation Checklist

### Phase 1: Foundation
- [ ] `UnifiedSubscription` DTO
- [ ] `SubscriptionStatus` normalized enum
- [ ] Gateway detection utility
- [ ] `GatewayBadge` component

### Phase 2: Resource Table
- [ ] `UnifiedSubscriptionResource`
- [ ] Table with all columns
- [ ] Gateway badge column
- [ ] Status normalization

### Phase 3: Filters & Tabs
- [ ] Gateway filter
- [ ] Status filter
- [ ] Gateway tabs
- [ ] Issues tab

### Phase 4: View Page
- [ ] Infolist with overview
- [ ] Gateway-specific details section
- [ ] External dashboard links

### Phase 5: Actions
- [ ] Cancel action (gateway-aware)
- [ ] Resume action
- [ ] Swap plan action
- [ ] Cancel immediately action

### Phase 6: Create Form
- [ ] Wizard with gateway selection
- [ ] Dynamic plan options
- [ ] Payment method per gateway
- [ ] Create handler with delegation

### Phase 7: Bulk Actions
- [ ] Bulk cancel
- [ ] Export CSV

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-multi-gateway-dashboard.md](03-multi-gateway-dashboard.md)
