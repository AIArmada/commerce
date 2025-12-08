# Filament Cashier Vision: Customer Portal

> **Document:** 04 of 05  
> **Package:** `aiarmada/filament-cashier`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

The Cross-Gateway Customer Portal provides a single interface for customers to manage ALL their billing across multiple payment gateways. A customer with a Stripe subscription and a CHIP subscription can view, manage, and pay from one unified portal.

---

## Portal Architecture

### Unified Billing Panel

```php
// app/Providers/Filament/BillingPanelProvider.php

use AIArmada\FilamentCashier\FilamentCashierPlugin;

class BillingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('billing')
            ->path('billing')
            ->login()
            ->plugins([
                FilamentCashierPlugin::make()
                    ->customerPortalMode()
                    ->showAllSubscriptions()
                    ->showAllPaymentMethods()
                    ->showAllInvoices()
                    ->enableGatewaySelection(),
            ])
            ->authGuard('web')
            ->middleware(['verified']);
    }
}
```

### URL Structure

```
/billing                         → Unified dashboard
/billing/subscriptions           → All subscriptions (any gateway)
/billing/subscriptions/{id}      → Single subscription details
/billing/payment-methods         → All payment methods (all gateways)
/billing/invoices                → All invoices (all gateways)
/billing/settings                → Billing information
```

---

## Portal Dashboard

### Layout

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 🏠 My Billing Dashboard                                    [Logout: John]  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 📦 Your Subscriptions                               [Manage All →]  │   │
│  │                                                                      │   │
│  │ ┌───────────────────────────────────┐ ┌─────────────────────────────┐│   │
│  │ │ 💳 Stripe: Premium Annual         │ │ 🔷 CHIP: Pro Monthly        ││   │
│  │ │ 🟢 Active                         │ │ 🟢 Active                   ││   │
│  │ │ $299/year                         │ │ RM 99/month                 ││   │
│  │ │ Renews: Dec 15, 2025              │ │ Renews: Jan 1, 2026         ││   │
│  │ │ [Manage]                          │ │ [Manage]                    ││   │
│  │ └───────────────────────────────────┘ └─────────────────────────────┘│   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────┐ ┌─────────────────────────────────────────┐│
│  │ 💳 Payment Methods          │ │ 📄 Recent Invoices                      ││
│  │                              │ │                                         ││
│  │ 💳 Stripe:                  │ │ 💳 Dec 2025 - $299   - ✓ Paid           ││
│  │   Visa •••• 4242 (Default)  │ │ 🔷 Dec 2025 - RM 99  - ✓ Paid           ││
│  │                              │ │ 💳 Nov 2025 - $299   - ✓ Paid           ││
│  │ 🔷 CHIP:                    │ │ 🔷 Nov 2025 - RM 99  - ✓ Paid           ││
│  │   Maybank FPX (Default)     │ │                                         ││
│  │                              │ │ [View All Invoices]                     ││
│  │ [Manage Payment Methods]    │ │                                         ││
│  └─────────────────────────────┘ └─────────────────────────────────────────┘│
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Dashboard Widget Implementation

```php
class UnifiedBillingOverviewWidget extends Widget
{
    protected static string $view = 'filament-cashier::widgets.unified-billing-overview';

    public function getAllSubscriptions(): Collection
    {
        return auth()->user()->allSubscriptions();
    }

    public function getPaymentMethodsByGateway(): array
    {
        $methods = [];
        
        foreach (Cashier::manager()->availableGateways() as $gateway) {
            $methods[$gateway] = auth()->user()->gatewayPaymentMethods($gateway);
        }
        
        return $methods;
    }

    public function getRecentInvoices(): Collection
    {
        return auth()->user()->allGatewayInvoices()
            ->sortByDesc('created_at')
            ->take(5);
    }
}
```

---

## All Subscriptions Page

### Unified Subscription List

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 📦 My Subscriptions                                                         │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  [All] [💳 Stripe] [🔷 CHIP]                                               │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 💳 Stripe                                                           │   │
│  │ Premium Annual                              Status: 🟢 Active        │   │
│  │                                                                      │   │
│  │ $299.00/year                                 Renews: Dec 15, 2025   │   │
│  │ Started: December 15, 2024                                          │   │
│  │                                                                      │   │
│  │ [View Details] [Change Plan] [Cancel Subscription]                  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔷 CHIP                                                             │   │
│  │ Pro Monthly                                  Status: 🟢 Active       │   │
│  │                                                                      │   │
│  │ RM 99.00/month                               Renews: Jan 1, 2026    │   │
│  │ Started: October 1, 2025                                            │   │
│  │                                                                      │   │
│  │ [View Details] [Change Plan] [Cancel Subscription]                  │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔷 CHIP                                            🟡 On Trial       │   │
│  │ Basic Monthly                                                        │   │
│  │                                                                      │   │
│  │ RM 29.00/month                          Trial ends: Dec 20, 2025    │   │
│  │ Trial started: December 6, 2025          (11 days remaining)        │   │
│  │                                                                      │   │
│  │ [View Details] [Upgrade Now] [Cancel Trial]                         │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│                                       [+ Add New Subscription]             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Page Implementation

```php
class AllSubscriptionsPage extends Page
{
    protected static string $view = 'filament-cashier::pages.all-subscriptions';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'My Subscriptions';

    public ?string $activeTab = 'all';

    public function getSubscriptions(): Collection
    {
        $subscriptions = auth()->user()->allSubscriptions();

        if ($this->activeTab !== 'all') {
            $subscriptions = $subscriptions->filter(
                fn ($sub) => $sub->gateway === $this->activeTab
            );
        }

        return $subscriptions->sortByDesc('created_at');
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All')
                ->badge(auth()->user()->allSubscriptions()->count()),
        ];

        foreach (Cashier::manager()->availableGateways() as $gateway) {
            $count = auth()->user()->gatewaySubscriptions($gateway)->count();
            if ($count > 0) {
                $tabs[$gateway] = Tab::make($this->getGatewayLabel($gateway))
                    ->badge($count);
            }
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSubscription')
                ->label('Add New Subscription')
                ->icon('heroicon-o-plus')
                ->url(route('filament.billing.pages.new-subscription')),
        ];
    }
}
```

---

## Subscription Management Actions

### View Subscription Details

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 💳 Stripe: Premium Annual                                      [← Back]    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Status: 🟢 Active                                                          │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Plan Details                                                         │   │
│  ├─────────────────────────────────────────────────────────────────────┤   │
│  │ Plan:              Premium Annual                                    │   │
│  │ Price:             $299.00/year                                      │   │
│  │ Quantity:          1 seat                                            │   │
│  │                                                                      │   │
│  │ Billing Cycle:                                                       │   │
│  │ Current period:    Dec 15, 2024 → Dec 15, 2025                       │   │
│  │ Next billing:      December 15, 2025                                 │   │
│  │ Amount due:        $299.00                                           │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Payment Method                                                       │   │
│  ├─────────────────────────────────────────────────────────────────────┤   │
│  │ 💳 Visa ending in 4242                                               │   │
│  │ Expires: 12/2027                                                     │   │
│  │                                       [Update Payment Method]        │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Actions                                                              │   │
│  ├─────────────────────────────────────────────────────────────────────┤   │
│  │ [Change Plan]        [Update Quantity]        [Cancel Subscription] │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Change Plan (Gateway-Aware)

```php
Action::make('changePlan')
    ->label('Change Plan')
    ->icon('heroicon-o-arrows-right-left')
    ->form(fn (UnifiedSubscription $subscription) => [
        Section::make('Current Plan')
            ->schema([
                Placeholder::make('current')
                    ->content("{$subscription->planId} - " . 
                        Number::currency($subscription->amount, $subscription->currency)),
            ]),
            
        Section::make('Available Plans on ' . ucfirst($subscription->gateway))
            ->schema([
                Radio::make('new_plan')
                    ->label('Select New Plan')
                    ->options($this->getPlansForGateway($subscription->gateway))
                    ->required(),
                    
                Placeholder::make('proration_info')
                    ->visible(fn () => $subscription->gateway === 'stripe')
                    ->content('Your card will be charged or credited the prorated difference.'),
            ]),
    ])
    ->action(function (UnifiedSubscription $subscription, array $data) {
        // Delegate to gateway-specific swap
        $subscription->original->swap($data['new_plan']);
        
        Notification::make()
            ->title('Plan changed successfully')
            ->success()
            ->send();
    });
```

### Cancel Subscription (With Gateway Context)

```php
Action::make('cancel')
    ->label('Cancel Subscription')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->modalHeading(fn (UnifiedSubscription $sub) => 
        'Cancel ' . ucfirst($sub->gateway) . ' Subscription'
    )
    ->form([
        Radio::make('reason')
            ->label('Why are you canceling?')
            ->options([
                'too_expensive' => 'Too expensive',
                'not_using' => 'Not using it enough',
                'switching_plan' => 'Switching to a different plan',
                'switching_gateway' => 'Using a different payment method',
                'other' => 'Other reason',
            ])
            ->required(),
            
        Textarea::make('feedback')
            ->label('Any feedback for us?'),
            
        Placeholder::make('retention')
            ->visible(fn (UnifiedSubscription $sub) => $sub->amount > 5000)
            ->content(function (UnifiedSubscription $sub) {
                $discount = $sub->amount * 0.2;
                return "Before you go: We'd love to keep you! 
                        Would a 20% discount (" . 
                        Number::currency($discount, $sub->currency) . 
                        "/month) help?";
            }),
            
        Checkbox::make('confirm')
            ->label(fn (UnifiedSubscription $sub) => 
                "I understand I'll retain access until " . 
                $sub->original->ends_at?->format('F j, Y'))
            ->required(),
    ])
    ->action(function (UnifiedSubscription $subscription, array $data) {
        // Store feedback
        $this->storeCancellationFeedback($subscription, $data);
        
        // Cancel via the original subscription
        $subscription->original->cancel();
        
        Notification::make()
            ->title('Subscription canceled')
            ->success()
            ->send();
    });
```

---

## Payment Methods Page

### Multi-Gateway Payment Methods

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 💳 Payment Methods                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  💳 Stripe Payment Methods                                                  │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ⭐ Default                                                           │   │
│  │ 💳 Visa ending in 4242                                               │   │
│  │ Expires: 12/2027                                                     │   │
│  │                                                           [Remove]   │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 💳 Mastercard ending in 5555                                         │   │
│  │ Expires: 06/2026                                                     │   │
│  │                                        [Set as Default] [Remove]     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│  [+ Add Stripe Payment Method]                                              │
│                                                                             │
│  ─────────────────────────────────────────────────────────────────────────  │
│                                                                             │
│  🔷 CHIP Payment Methods                                                    │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ⭐ Default                                                           │   │
│  │ 🏦 Maybank FPX                                                       │   │
│  │ Recurring Token: tok_••••1234                                        │   │
│  │                                                           [Remove]   │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│  [+ Add CHIP Payment Method]                                                │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Add Payment Method (Gateway-Specific Flow)

```php
Action::make('addPaymentMethod')
    ->label('Add Payment Method')
    ->form([
        Select::make('gateway')
            ->label('Payment Gateway')
            ->options(fn () => collect(Cashier::manager()->availableGateways())
                ->mapWithKeys(fn ($g) => [$g => $this->getGatewayLabel($g)])
                ->toArray())
            ->required()
            ->live(),
            
        Placeholder::make('stripe_info')
            ->visible(fn (Get $get) => $get('gateway') === 'stripe')
            ->content('You will be redirected to securely add a card via Stripe.'),
            
        Placeholder::make('chip_info')
            ->visible(fn (Get $get) => $get('gateway') === 'chip')
            ->content('You will complete a zero-amount authorization to save your payment method.'),
    ])
    ->action(function (array $data) {
        $gateway = $data['gateway'];
        $user = auth()->user();
        
        $redirectUrl = match ($gateway) {
            'stripe' => $user->gateway('stripe')->createSetupIntentUrl([
                'success_url' => route('filament.billing.pages.payment-methods'),
                'cancel_url' => route('filament.billing.pages.payment-methods'),
            ]),
            'chip' => $user->gateway('chip')->createSetupPurchaseUrl([
                'success_url' => route('filament.billing.pages.payment-methods'),
                'cancel_url' => route('filament.billing.pages.payment-methods'),
            ]),
        };
        
        return redirect($redirectUrl);
    });
```

---

## All Invoices Page

### Multi-Gateway Invoice List

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 📄 Invoice History                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  [All] [💳 Stripe] [🔷 CHIP]                         [Year: 2025 ▾]        │
│                                                                             │
│  ┌──────────┬──────────┬────────────────┬──────────────┬──────────────────┐ │
│  │ Date     │ Gateway  │ Description    │ Amount       │ Status           │ │
│  ├──────────┼──────────┼────────────────┼──────────────┼──────────────────┤ │
│  │ Dec 15   │ 💳       │ Premium Annual │ $299.00      │ ✓ Paid    [📥]   │ │
│  │ Dec 1    │ 🔷       │ Pro Monthly    │ RM 99.00     │ ✓ Paid    [📥]   │ │
│  │ Nov 15   │ 💳       │ Premium Annual │ $299.00      │ ✓ Paid    [📥]   │ │
│  │ Nov 1    │ 🔷       │ Pro Monthly    │ RM 99.00     │ ✓ Paid    [📥]   │ │
│  │ Oct 1    │ 🔷       │ Pro Monthly    │ RM 99.00     │ ✓ Paid    [📥]   │ │
│  │ Oct 1    │ 🔷       │ Upgrade        │ RM 70.00     │ ✓ Paid    [📥]   │ │
│  └──────────┴──────────┴────────────────┴──────────────┴──────────────────┘ │
│                                                                             │
│                                                  Showing 1-25 of 48         │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Page Implementation

```php
class AllInvoicesPage extends Page
{
    protected static string $view = 'filament-cashier::pages.all-invoices';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public ?string $activeTab = 'all';
    public ?string $year = null;

    public function mount(): void
    {
        $this->year = now()->year;
    }

    public function getInvoices(): Collection
    {
        $invoices = auth()->user()->allGatewayInvoices();

        // Filter by gateway
        if ($this->activeTab !== 'all') {
            $invoices = $invoices->filter(
                fn ($invoice) => $invoice->gateway === $this->activeTab
            );
        }

        // Filter by year
        if ($this->year) {
            $invoices = $invoices->filter(
                fn ($invoice) => $invoice->created_at->year == $this->year
            );
        }

        return $invoices->sortByDesc('created_at');
    }

    public function downloadInvoice(string $invoiceId, string $gateway): StreamedResponse
    {
        $invoice = auth()->user()
            ->gateway($gateway)
            ->findInvoice($invoiceId);
            
        return response()->streamDownload(
            fn () => print $invoice->pdf(),
            "invoice-{$invoiceId}.pdf"
        );
    }
}
```

---

## New Subscription Flow (Gateway Selection)

### Create New Subscription

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ➕ Add New Subscription                                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Step 1 of 3: Choose Payment Gateway                                       │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ○ 💳 Stripe                                                          │   │
│  │   Credit/debit cards, ACH bank transfers                             │   │
│  │   Best for: International payments, recurring cards                  │   │
│  │   Currencies: USD, EUR, GBP, and 135+ more                           │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ ● 🔷 CHIP                                                            │   │
│  │   FPX direct debit, Malaysian e-wallets                              │   │
│  │   Best for: Malaysian customers, lower fees                          │   │
│  │   Currencies: MYR                                                    │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│                                               [Cancel] [Next: Choose Plan]  │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Wizard Implementation

```php
class NewSubscriptionPage extends Page
{
    protected static string $view = 'filament-cashier::pages.new-subscription';

    public ?string $gateway = null;
    public ?string $planId = null;
    public ?string $paymentMethod = null;

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Gateway')
                    ->label('Choose Gateway')
                    ->schema([
                        Radio::make('gateway')
                            ->label('Payment Gateway')
                            ->options($this->getGatewayOptions())
                            ->descriptions($this->getGatewayDescriptions())
                            ->required()
                            ->live(),
                    ]),

                Wizard\Step::make('Plan')
                    ->label('Choose Plan')
                    ->schema([
                        Placeholder::make('gateway_selected')
                            ->content(fn (Get $get) => 
                                'Plans available on ' . ucfirst($get('gateway'))),
                                
                        Radio::make('plan_id')
                            ->label('Select Plan')
                            ->options(fn (Get $get) => 
                                $this->getPlansForGateway($get('gateway')))
                            ->descriptions(fn (Get $get) => 
                                $this->getPlanDescriptions($get('gateway')))
                            ->required(),
                    ]),

                Wizard\Step::make('Payment')
                    ->label('Payment Method')
                    ->schema([
                        Radio::make('payment_method')
                            ->label('Payment Method')
                            ->options(fn (Get $get) => 
                                $this->getPaymentMethodsForGateway($get('gateway')))
                            ->required(),
                            
                        Action::make('addNew')
                            ->label('Add New Payment Method')
                            ->action(fn (Get $get) => 
                                $this->redirectToAddPaymentMethod($get('gateway'))),
                    ]),

                Wizard\Step::make('Confirm')
                    ->label('Confirm')
                    ->schema([
                        Placeholder::make('summary')
                            ->content(fn (Get $get) => 
                                $this->getSubscriptionSummary($get('gateway'), $get('plan_id'))),
                                
                        Checkbox::make('terms')
                            ->label('I agree to the terms of service')
                            ->required(),
                    ]),
            ])
            ->submitAction(new Action('subscribe'))
        ];
    }

    public function subscribe(): void
    {
        $user = auth()->user();
        
        $subscription = $user->newGatewaySubscription(
            type: 'default',
            price: $this->planId,
            gateway: $this->gateway
        );

        if ($this->paymentMethod) {
            $subscription->create($this->paymentMethod);
        } else {
            $subscription->create();
        }

        Notification::make()
            ->title('Subscription created!')
            ->success()
            ->send();

        $this->redirect(route('filament.billing.pages.subscriptions'));
    }
}
```

---

## Implementation Checklist

### Phase 1: Portal Foundation
- [ ] Billing panel provider
- [ ] Customer authentication
- [ ] Portal mode in plugin

### Phase 2: Dashboard
- [ ] Unified billing overview widget
- [ ] Subscription cards
- [ ] Payment method preview
- [ ] Recent invoices

### Phase 3: Subscriptions Page
- [ ] List all subscriptions
- [ ] Gateway tabs
- [ ] Subscription cards with actions
- [ ] View details page

### Phase 4: Subscription Actions
- [ ] Change plan (gateway-aware)
- [ ] Cancel subscription
- [ ] Resume subscription
- [ ] Update quantity

### Phase 5: Payment Methods
- [ ] List by gateway sections
- [ ] Add method per gateway
- [ ] Set default per gateway
- [ ] Remove method

### Phase 6: Invoices
- [ ] List all invoices
- [ ] Gateway filter
- [ ] Year filter
- [ ] Download PDF

### Phase 7: New Subscription
- [ ] Gateway selection step
- [ ] Plan selection step
- [ ] Payment method step
- [ ] Confirmation step

---

## Navigation

**Previous:** [03-multi-gateway-dashboard.md](03-multi-gateway-dashboard.md)  
**Next:** [05-implementation-roadmap.md](05-implementation-roadmap.md)
