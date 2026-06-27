---
title: Usage
---

# Usage

This guide covers common usage patterns for Filament Cashier.

## Admin Panel Usage

### Viewing Subscriptions

Navigate to **Billing → Subscriptions** to see all subscriptions across gateways.

The unified subscriptions resource is DTO-backed: it aggregates records from installed gateways, normalizes them into one table, and keeps gateway-specific actions delegated to the original subscription model.

The subscription list shows:
- **Customer** - The user who owns the subscription
- **Gateway** - Stripe or CHIP badge
- **Type** - Subscription type/name
- **Plan** - The price/plan ID
- **Status** - Active, On Trial, Canceled, etc.
- **Amount** - Monthly amount
- **Next Billing** - Next billing date

### Filtering Subscriptions

Use the tabs to filter by:
- **All** - All subscriptions
- **Stripe** / **CHIP** - Filter by gateway
- **Active** - Only active subscriptions
- **Needs Attention** - Past due or incomplete

Use the dropdown filters for:
- Gateway
- Status (Active, On Trial, Past Due, Canceled, etc.)

### Subscription Actions

On each subscription row:

| Action | Description |
|--------|-------------|
| **View** | See subscription details |
| **Cancel** | Cancel at period end |
| **Resume** | Resume a canceled subscription |
| **View in Gateway** | Open gateway dashboard |

### Creating Subscriptions

Click **Create Subscription** and follow the wizard:

1. **Customer** - Select the user
2. **Gateway** - Choose Stripe or CHIP
3. **Plan** - Select plan and quantity
4. **Payment** - Choose payment method (optional)

### Viewing Invoices

Navigate to **Billing → Invoices** to see all invoices.

Available actions:
- **Download** - Download PDF invoice
- **View in Gateway** - Open gateway dashboard
- **Export** - Bulk export to CSV

The unified invoices resource is list-first: it aggregates invoice records across installed gateways and exposes download, export, and external-dashboard actions without pretending the underlying gateway data lives in one table.

## Dashboard Widgets

### Total MRR Widget

Shows combined Monthly Recurring Revenue across all gateways.

```php
// Widget uses config for currency conversion
'currency' => [
    'base' => 'USD',
    'display_converted' => true, // Enable to convert MYR → USD
],
```

### Gateway Breakdown Widget

Doughnut chart showing revenue distribution by gateway.

### Gateway Comparison Widget

Line chart comparing 6-month revenue trends per gateway.

### Churn Widget

Shows monthly cancellations with trend indicator.

## Customer Portal

### Enabling the Portal

1. Enable in config:
```php
'billing_portal' => [
    'enabled' => true,
    'path' => 'billing',
],
```

2. Register the panel provider:
```php
// config/app.php
'providers' => [
    AIArmada\FilamentCashier\CustomerPortal\BillingPanelProvider::class,
],
```

3. Access at `/billing`

### Portal Pages

| Page | Purpose |
|------|---------|
| **Billing Overview** | Dashboard with subscriptions, payment methods, invoices |
| **Manage Subscriptions** | View, cancel, resume subscriptions |
| **Payment Methods** | View, add, remove, set default payment methods |
| **View Invoices** | List and download invoices |

### Billing Overview Widgets

The overview page can surface three customer-facing preview widgets:

- `ActiveSubscriptionsWidget`
- `PaymentMethodsPreviewWidget`
- `RecentInvoicesWidget`

These widgets only show records for the authenticated user and rely on `CashierOwnerScope` plus the current auth identifier when loading Stripe and CHIP data.

### Manage Subscriptions Behavior

The `ManageSubscriptions` portal page merges Stripe and CHIP subscriptions for the authenticated user, sorts them by newest first, and keeps a per-gateway fetch limit so the portal can progressively load more records without querying the full history every time.

Portal subscription actions are owner-scoped and policy-checked before mutation:

- `cancelSubscription()` only runs when the normalized status is cancelable and `SubscriptionPolicy` authorizes the underlying record
- `resumeSubscription()` only runs when the normalized status is resumable and policy checks pass
- the optional `new subscription` header action is shown only when subscriptions are enabled for the billing portal and the create route exists on the active panel

### Customer Actions

Customers can:
- View all their subscriptions across gateways
- Cancel subscriptions (at period end)
- Resume canceled subscriptions (if on grace period)
- View payment methods from all gateways
- Set default payment method per gateway
- Delete payment methods
- View and download invoices
- See a compact overview of active subscriptions, saved payment methods, and recent invoices from the billing dashboard

## Gateway Management

Enable the gateway management page:

```php
FilamentCashierPlugin::make()
    ->gatewayManagement()
```

### Features

- **Gateway Health** - Check connectivity to each gateway
- **Set Default** - Configure the default gateway
- **Test Connection** - Verify API credentials

### Health Statuses

| Status | Color | Meaning |
|--------|-------|---------|
| Healthy | Green | API responding correctly |
| Not Configured | Yellow | Missing credentials |
| Error | Red | API error occurred |
| Unknown | Gray | SDK not installed |

## Customization

### Custom Gateway Labels

```php
// config/filament-cashier.php
'gateways' => [
    'stripe' => [
        'label' => 'International Cards',
        'icon' => 'heroicon-o-globe-alt',
    ],
    'chip' => [
        'label' => 'Malaysian Payments',
        'icon' => 'heroicon-o-building-library',
    ],
],
```

### Custom Navigation Group

```php
FilamentCashierPlugin::make()
    ->navigationGroup('Finance & Billing')
    ->navigationSort(20)
```

### Disabling Resources

```php
FilamentCashierPlugin::make()
    ->dashboard(false)  // Hide dashboard
    ->invoices(false)   // Hide invoices
```

## Working with DTOs

The package uses DTOs to normalize data across gateways.

### UnifiedSubscription

```php
use AIArmada\FilamentCashier\Support\UnifiedSubscription;

// Properties
$sub->id           // Subscription ID
$sub->gateway      // 'stripe' or 'chip'
$sub->userId       // User ID
$sub->type         // Subscription type
$sub->planId       // Price/plan ID
$sub->amount       // Amount in cents
$sub->currency     // Currency code
$sub->quantity     // Quantity
$sub->status       // SubscriptionStatus enum
$sub->trialEndsAt  // CarbonImmutable|null
$sub->endsAt       // CarbonImmutable|null
$sub->nextBillingDate // CarbonImmutable|null
$sub->createdAt    // CarbonImmutable
$sub->original     // Original Eloquent model

// Methods
$sub->formattedAmount()       // "$29.00"
$sub->billingCycle()          // "Monthly"
$sub->needsAttention()        // true if past_due/incomplete
$sub->gatewayConfig()         // Gateway config array
$sub->externalDashboardUrl()  // Gateway dashboard URL
$sub->getExternalId()         // Gateway-specific ID
```

Normalization details that matter in practice:

- Stripe `planId` falls back through `stripe_price`, `name`, and then `type`.
- CHIP `planId` falls back through `plan_id`, `name`, and then `type`.
- Stripe `amount` is derived from the first available subscription item (`quantity × unit_amount`) when possible.
- CHIP `amount` uses the stored `amount` field first, then falls back to summing subscription items.
- `getExternalId()` and `externalDashboardUrl()` use gateway-specific record identifiers such as Stripe customer/subscription IDs and CHIP subscription IDs when present.

### UnifiedInvoice

```php
use AIArmada\FilamentCashier\Support\UnifiedInvoice;

// Properties
$invoice->id       // Invoice ID
$invoice->gateway  // 'stripe' or 'chip'
$invoice->userId   // User ID
$invoice->number   // Invoice number
$invoice->amount   // Amount in cents
$invoice->currency // Currency code
$invoice->status   // InvoiceStatus enum
$invoice->date     // CarbonImmutable
$invoice->dueDate  // CarbonImmutable|null
$invoice->paidAt   // CarbonImmutable|null
$invoice->pdfUrl   // PDF download URL

// Methods
$invoice->formattedAmount()     // "RM 99.00"
$invoice->gatewayConfig()       // Gateway config array
$invoice->externalDashboardUrl() // Gateway dashboard URL
```

### Status Enums

```php
use AIArmada\FilamentCashier\Support\SubscriptionStatus;

SubscriptionStatus::Active
SubscriptionStatus::OnTrial
SubscriptionStatus::PastDue
SubscriptionStatus::Canceled
SubscriptionStatus::OnGracePeriod
SubscriptionStatus::Paused
SubscriptionStatus::Incomplete
SubscriptionStatus::Expired

// Methods
$status->label()        // "Active"
$status->color()        // "success"
$status->icon()         // "heroicon-o-check-circle"
$status->isActive()     // true for Active, OnTrial, OnGracePeriod
$status->isCancelable() // true for Active, OnTrial, PastDue
$status->isResumable()  // true for OnGracePeriod, Paused
```

## Currency Formatting

Use the shared `MoneyFormatter` utility:

```php
use AIArmada\CommerceSupport\Support\MoneyFormatter;

MoneyFormatter::formatMinor(2900, 'USD');         // "$29.00"
MoneyFormatter::formatMinor(9900, 'MYR');         // "RM99.00"
MoneyFormatter::formatMinorWithCode(2900, 'USD'); // "29.00 USD"
MoneyFormatter::symbol('MYR');                    // "RM"
MoneyFormatter::precisionFor('JPY') === 0;        // true
MoneyFormatter::formatMinor(10000, 'JPY');        // "¥10,000"
```

## Events

The package doesn't dispatch its own events but works with events from the underlying cashier packages.

Listen to events from `aiarmada/cashier`:
- `PaymentSucceeded`
- `PaymentFailed`
- `SubscriptionCreated`
- `SubscriptionCanceled`
- etc.

See the [cashier package documentation](../../../cashier/docs/05-webhooks.md) for details.
