# Filament Cashier Chip Vision: Customer Billing Portal

> **Document:** 03 of 05  
> **Package:** `aiarmada/filament-cashier-chip`  
> **Status:** Vision Blueprint  
> **Last Updated:** December 9, 2025

---

## Overview

Unlike Stripe's hosted billing portal, CHIP does not provide a customer-facing billing management interface. This package implements a **self-hosted Filament panel** allowing customers to:

- View and manage their subscriptions
- Add, remove, and update payment methods
- View billing history and download invoices
- Update billing information

---

## Portal Architecture

### Dedicated Filament Panel

```php
// app/Providers/Filament/BillingPanelProvider.php

use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;

class BillingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('billing')
            ->path('billing')
            ->login()
            ->registration(false)
            ->plugins([
                FilamentCashierChipPlugin::make()
                    ->customerPortalMode() // Enables customer-facing views only
                    ->showSubscriptionManagement()
                    ->showPaymentMethods()
                    ->showInvoiceHistory()
                    ->showBillingSettings(),
            ])
            ->authGuard('web')
            ->middleware(['verified', 'billable']);
    }
}
```

### URL Structure

```
/billing                         → Dashboard overview
/billing/subscription            → Current subscription details
/billing/payment-methods         → Manage payment methods
/billing/invoices                → Invoice history
/billing/settings                → Billing address/info
```

---

## Portal Dashboard

### Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ 🏠 Billing Dashboard                           [Logout: John]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ 📦 Your Subscription                                     │   │
│  │                                                          │   │
│  │ Premium Monthly              Status: 🟢 Active           │   │
│  │ RM 99.00/month               Next billing: Jan 15, 2026  │   │
│  │                                                          │   │
│  │ [Manage Subscription]                                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌───────────────────────┐  ┌───────────────────────────────┐   │
│  │ 💳 Payment Method      │  │ 📄 Recent Invoices           │   │
│  │                        │  │                               │   │
│  │ Visa ending in 4242    │  │ Dec 2025 - RM 99 - ✓ Paid     │   │
│  │ Expires: 12/2027       │  │ Nov 2025 - RM 99 - ✓ Paid     │   │
│  │                        │  │ Oct 2025 - RM 99 - ✓ Paid     │   │
│  │ [Update Card]          │  │                               │   │
│  └───────────────────────┘  │ [View All Invoices]           │   │
│                              └───────────────────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Dashboard Widget Implementation

```php
class BillingOverviewWidget extends Widget
{
    protected static string $view = 'filament-cashier-chip::widgets.billing-overview';

    public function getSubscription(): ?Subscription
    {
        return auth()->user()->subscription('default');
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return auth()->user()->defaultPaymentMethod();
    }

    public function getRecentInvoices(): Collection
    {
        return auth()->user()->invoices()->take(3);
    }
}
```

---

## Subscription Management Page

### Current Plan View

```
┌─────────────────────────────────────────────────────────────────┐
│ 📦 Subscription                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Current Plan                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Premium Monthly                                          │   │
│  │ RM 99.00/month                                           │   │
│  │                                                          │   │
│  │ Status: 🟢 Active                                        │   │
│  │ Member since: November 15, 2024                          │   │
│  │ Next billing date: January 15, 2026                      │   │
│  │                                                          │   │
│  │ ┌──────────────────┐ ┌────────────────────────────────┐  │   │
│  │ │  Change Plan     │ │  Cancel Subscription           │  │   │
│  │ └──────────────────┘ └────────────────────────────────┘  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Change Plan Action

```php
Action::make('changePlan')
    ->label('Change Plan')
    ->icon('heroicon-o-arrows-right-left')
    ->form([
        Radio::make('plan')
            ->label('Select New Plan')
            ->options([
                'price_basic_monthly' => Card::make([
                    'title' => 'Basic',
                    'price' => 'RM 29/month',
                    'features' => ['5 projects', 'Basic support'],
                ]),
                'price_pro_monthly' => Card::make([
                    'title' => 'Pro',
                    'price' => 'RM 99/month',
                    'features' => ['Unlimited projects', 'Priority support'],
                ]),
                'price_premium_annual' => Card::make([
                    'title' => 'Premium (Annual)',
                    'price' => 'RM 899/year (Save RM 289)',
                    'features' => ['Everything in Pro', 'Dedicated support'],
                ]),
            ])
            ->required(),
            
        Placeholder::make('proration_preview')
            ->label('Price Change')
            ->content(fn (Get $get) => static::getProrationPreview($get('plan'))),
    ])
    ->modalWidth('lg')
    ->action(function (array $data) {
        auth()->user()
            ->subscription('default')
            ->swap($data['plan']);
            
        Notification::make()
            ->title('Plan changed successfully')
            ->success()
            ->send();
    });
```

### Cancel Subscription Flow

```php
Action::make('cancel')
    ->label('Cancel Subscription')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->modalHeading('Cancel Your Subscription')
    ->form([
        Radio::make('reason')
            ->label('Why are you canceling?')
            ->options([
                'too_expensive' => 'Too expensive',
                'not_using' => 'Not using it enough',
                'missing_features' => 'Missing features I need',
                'found_alternative' => 'Found a better alternative',
                'other' => 'Other reason',
            ])
            ->required(),
            
        Textarea::make('feedback')
            ->label('Any feedback for us?')
            ->placeholder('Help us improve...'),
            
        Placeholder::make('access_info')
            ->content('You will retain access until your current billing period ends on **January 15, 2026**.'),
    ])
    ->action(function (array $data) {
        $subscription = auth()->user()->subscription('default');
        
        // Store cancellation feedback
        $subscription->update([
            'cancellation_reason' => $data['reason'],
            'cancellation_feedback' => $data['feedback'],
        ]);
        
        $subscription->cancel();
        
        Notification::make()
            ->title('Subscription canceled')
            ->body('You\'ll retain access until ' . $subscription->ends_at->format('F j, Y'))
            ->success()
            ->send();
    });
```

### Resume Subscription (Grace Period)

```php
Action::make('resume')
    ->label('Resume Subscription')
    ->icon('heroicon-o-play')
    ->visible(fn () => auth()->user()->subscription('default')?->onGracePeriod())
    ->requiresConfirmation()
    ->modalDescription('Your subscription will continue and you will be billed on the next billing date.')
    ->action(function () {
        auth()->user()->subscription('default')->resume();
        
        Notification::make()
            ->title('Welcome back!')
            ->body('Your subscription has been resumed.')
            ->success()
            ->send();
    });
```

---

## Payment Methods Page

### Payment Methods List

```
┌─────────────────────────────────────────────────────────────────┐
│ 💳 Payment Methods                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ ⭐ Primary                                               │   │
│  │ 💳 Visa ending in 4242                                   │   │
│  │ Expires: 12/2027                                         │   │
│  │                                                 [Remove]  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ 💳 Mastercard ending in 5555                             │   │
│  │ Expires: 06/2026                                         │   │
│  │                               [Set as Primary] [Remove]  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │         ➕ Add New Payment Method                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Add Payment Method Flow

```php
Action::make('addPaymentMethod')
    ->label('Add Payment Method')
    ->icon('heroicon-o-plus')
    ->action(function () {
        // Create setup purchase (zero-amount preauthorization)
        $checkout = auth()->user()->createSetupPurchase([
            'success_url' => route('filament.billing.pages.payment-methods', [
                'setup' => 'success',
            ]),
            'cancel_url' => route('filament.billing.pages.payment-methods'),
        ]);
        
        // Redirect to CHIP checkout
        return redirect($checkout->checkout_url);
    });

// After successful setup, webhook saves the recurring token
// Page reloads and shows the new payment method
```

### Set Default Payment Method

```php
Action::make('setDefault')
    ->label('Set as Primary')
    ->requiresConfirmation()
    ->action(function (PaymentMethod $record) {
        auth()->user()->updateDefaultPaymentMethod($record->recurring_token);
        
        Notification::make()
            ->title('Primary payment method updated')
            ->success()
            ->send();
    });
```

### Remove Payment Method

```php
Action::make('remove')
    ->label('Remove')
    ->color('danger')
    ->requiresConfirmation()
    ->modalDescription('This will delete this payment method. If it\'s your only payment method, you\'ll need to add a new one before your next billing date.')
    ->action(function (PaymentMethod $record) {
        // Prevent removing if it's the only method and has active subscription
        if (auth()->user()->hasActiveSubscription() 
            && auth()->user()->paymentMethods()->count() === 1) {
            Notification::make()
                ->title('Cannot remove')
                ->body('You need at least one payment method while subscribed.')
                ->danger()
                ->send();
            return;
        }
        
        auth()->user()->deletePaymentMethod($record->recurring_token);
        
        Notification::make()
            ->title('Payment method removed')
            ->success()
            ->send();
    });
```

---

## Invoice History Page

### Invoice List

```
┌─────────────────────────────────────────────────────────────────┐
│ 📄 Invoice History                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┬─────────────┬──────────────┬────────────────┐ │
│  │ Date         │ Description │ Amount       │ Actions        │ │
│  ├──────────────┼─────────────┼──────────────┼────────────────┤ │
│  │ Dec 15, 2025 │ Premium     │ RM 99.00     │ ✓ Paid [📥]   │ │
│  │ Nov 15, 2025 │ Premium     │ RM 99.00     │ ✓ Paid [📥]   │ │
│  │ Oct 15, 2025 │ Premium     │ RM 99.00     │ ✓ Paid [📥]   │ │
│  │ Sep 15, 2025 │ Premium     │ RM 99.00     │ ✓ Paid [📥]   │ │
│  │ Aug 15, 2025 │ Pro → Prem  │ RM 50.00     │ ✓ Paid [📥]   │ │
│  └──────────────┴─────────────┴──────────────┴────────────────┘ │
│                                                                 │
│                                         Showing 1-25 of 12      │
└─────────────────────────────────────────────────────────────────┘
```

### Download Invoice

```php
Action::make('download')
    ->label('Download')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function (Invoice $record) {
        return response()->streamDownload(
            fn () => print $record->pdf(),
            "invoice-{$record->id}.pdf"
        );
    });
```

### View Invoice Details

```php
Action::make('view')
    ->icon('heroicon-o-eye')
    ->modalContent(fn (Invoice $record) => view('filament-cashier-chip::invoice-detail', [
        'invoice' => $record,
    ]))
    ->modalSubmitAction(false)
    ->modalCancelActionLabel('Close');
```

---

## Billing Settings Page

### Billing Information Form

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚙️ Billing Settings                                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Billing Information                                            │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Name:     [John Doe                              ]       │   │
│  │ Email:    [john@example.com                      ]       │   │
│  │ Phone:    [+60123456789                          ]       │   │
│  │                                                          │   │
│  │ Address:  [123 Main Street                       ]       │   │
│  │ City:     [Kuala Lumpur    ] State: [W. Persekutuan]     │   │
│  │ Postcode: [50000           ] Country: [Malaysia   ]      │   │
│  │                                                          │   │
│  │ Tax ID:   [                                      ]       │   │
│  │                                                          │   │
│  │                                          [Save Changes]  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Form Implementation

```php
public function form(Form $form): Form
{
    return $form
        ->schema([
            Section::make('Billing Information')
                ->schema([
                    TextInput::make('billing_name')
                        ->label('Name')
                        ->required(),
                    TextInput::make('billing_email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    TextInput::make('billing_phone')
                        ->label('Phone')
                        ->tel(),
                ]),
                
            Section::make('Billing Address')
                ->schema([
                    TextInput::make('billing_address')
                        ->label('Address'),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('billing_city')
                                ->label('City'),
                            TextInput::make('billing_state')
                                ->label('State'),
                        ]),
                    Grid::make(2)
                        ->schema([
                            TextInput::make('billing_postcode')
                                ->label('Postcode'),
                            Select::make('billing_country')
                                ->label('Country')
                                ->options(Countries::all()),
                        ]),
                ]),
                
            Section::make('Tax Information')
                ->schema([
                    TextInput::make('tax_id')
                        ->label('Tax ID / SSM Number'),
                ]),
        ])
        ->statePath('data');
}

public function save(): void
{
    $data = $this->form->getState();
    
    auth()->user()->update($data);
    
    // Sync to CHIP if customer exists
    if (auth()->user()->hasChipId()) {
        auth()->user()->updateChipCustomer();
    }
    
    Notification::make()
        ->title('Billing information updated')
        ->success()
        ->send();
}
```

---

## Portal Access Integration

### Getting Portal URL

```php
// In application code
$portalUrl = auth()->user()->billingPortalUrl([
    'return_url' => route('dashboard'),
]);

return redirect($portalUrl);
```

### Middleware for Billable Users

```php
// app/Http/Middleware/EnsureBillable.php
class EnsureBillable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->hasChipId()) {
            return redirect()->route('billing.setup');
        }
        
        return $next($request);
    }
}
```

---

## Implementation Checklist

### Phase 1: Panel Setup
- [ ] Dedicated billing panel provider
- [ ] Customer portal mode in plugin
- [ ] Billable middleware
- [ ] Portal URL generation

### Phase 2: Dashboard
- [ ] Billing overview widget
- [ ] Subscription summary card
- [ ] Payment method preview
- [ ] Recent invoices

### Phase 3: Subscription Page
- [ ] Current plan display
- [ ] Change plan flow
- [ ] Cancel subscription flow
- [ ] Resume subscription action

### Phase 4: Payment Methods
- [ ] List payment methods
- [ ] Add via setup purchase
- [ ] Set default method
- [ ] Remove method

### Phase 5: Invoices & Settings
- [ ] Invoice history list
- [ ] Download PDF
- [ ] Billing info form
- [ ] Sync to CHIP

---

## Navigation

**Previous:** [02-subscription-management.md](02-subscription-management.md)  
**Next:** [04-dashboard-widgets.md](04-dashboard-widgets.md)
