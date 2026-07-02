---
title: Usage Guide
---

# Usage Guide

## Making a Model Ticketable

Any Eloquent model can sell tickets by implementing `TicketableInterface`:

```php
use AIArmada\Ticketing\Contracts\TicketableInterface;
use AIArmada\Ticketing\Enums\PricingMode;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Workshop extends Model implements TicketableInterface
{
    public function ticketTypes(): MorphMany
    {
        return $this->morphMany(TicketType::class, 'ticketable');
    }

    public function passes(): MorphMany
    {
        return $this->morphMany(Pass::class, 'ticketable');
    }

    public function effectivePricingMode(): PricingMode
    {
        return PricingMode::Flat;
    }

    public function transferWindowEndsAt(): ?CarbonImmutable
    {
        return $this->starts_at?->subHours(24);
    }
}
```

The `TicketableInterface` requires four methods:

| Method | Returns | Description |
|--------|---------|-------------|
| `ticketTypes()` | `MorphMany` | Relationship to ticket types |
| `passes()` | `MorphMany` | Relationship to passes |
| `effectivePricingMode()` | `PricingMode` | Default pricing mode for this model |
| `transferWindowEndsAt()` | `?CarbonImmutable` | Deadline for pass transfers, or null for no limit |

## Creating Ticket Types

Use `EnsureTicketTypeAction` to create or update a ticket type:

```php
use AIArmada\Ticketing\Actions\EnsureTicketTypeAction;

$ticketType = app(EnsureTicketTypeAction::class)->handle($workshop, [
    'name' => 'General Admission',
    'code' => 'GA',
    'price' => 50000, // 500.00 in minor units
    'currency' => 'MYR',
    'max_quantity' => 5,
    'sales_starts_at' => now()->subDay(),
    'sales_ends_at' => $workshop->starts_at,
]);
```

### Ticket Type Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `name` | `string` | Display name |
| `code` | `string` | Unique code for the ticket type |
| `price` | `int` | Price in minor units (e.g., cents/sen) |
| `currency` | `string` | ISO 4217 currency code |
| `max_quantity` | `?int` | Max tickets per purchase (null = unlimited) |
| `sales_starts_at` | `?CarbonImmutable` | When sales open (null = immediately) |
| `sales_ends_at` | `?CarbonImmutable` | When sales close (null = no end) |
| `capacity` | `?int` | Total capacity for this ticket type |
| `pricing_mode` | `?PricingMode` | Override pricing mode (defaults to ticketable's mode) |

### Pricing Components

Split pricing into components:

```php
$ticketType = app(EnsureTicketTypeAction::class)->handle($workshop, [
    'name' => 'General Admission',
    'code' => 'GA',
    'price' => 55000,
    'currency' => 'MYR',
    'components' => [
        ['name' => 'Base Ticket', 'amount' => 50000],
        ['name' => 'Processing Fee', 'amount' => 5000],
    ],
]);
```

### Bundle Products

Link products that should be auto-added to cart when this ticket type is selected:

```php
$ticketType = app(EnsureTicketTypeAction::class)->handle($workshop, [
    'name' => 'VIP with Merch',
    'code' => 'VIP',
    'price' => 150000,
    'currency' => 'MYR',
    'products' => [
        ['product_id' => $tShirt->getKey(), 'quantity' => 1],
    ],
]);
```

> **Note**: Bundle products require `aiarmada/products` and `aiarmada/cart` to be installed.

## Issuing Passes

Use `IssuePassesAction` to issue passes:

```php
use AIArmada\Ticketing\Actions\IssuePassesAction;
use AIArmada\Ticketing\Data\PassIssuanceContext;

$context = new PassIssuanceContext(
    ticketType: $ticketType,
    quantity: 2,
    holderAttributes: [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ],
);

$passes = app(IssuePassesAction::class)->handle($context);
```

Each pass receives:
- A unique pass number (e.g., `PASS-000001`)
- A QR code and barcode
- Its initial state (`Issued`)

## Transferring a Pass

### Single Transfer

```php
use AIArmada\Ticketing\Actions\TransferPassToHolderAction;
use AIArmada\Ticketing\Models\PassHolder;

$newHolder = PassHolder::make([
    'name' => 'Charlie',
    'email' => 'charlie@example.com',
]);

app(TransferPassToHolderAction::class)->handle(
    pass: $pass,
    newHolder: $newHolder,
    reason: 'Gift',
);
```

### Checking Transfer Eligibility

```php
if ($pass->canTransfer()) {
    // Pass is in a non-terminal state and within transfer window
}

if ($pass->transferExpired()) {
    // Past the transfer window deadline
}
```

A pass can transfer when it is in a non-terminal state (not Used, Revoked, Voided, or Expired) and not past its `transfer_expires_at` date.

## Bulk Transfer

Transfer multiple passes at once:

```php
use AIArmada\Ticketing\Actions\BulkTransferPassesAction;

$result = app(BulkTransferPassesAction::class)->handle(
    passIds: [$pass1->getKey(), $pass2->getKey(), $pass3->getKey()],
    newHolder: $customer,
    reason: 'Bulk gift for corporate event',
);

$holders = $result; // Collection of newly current pass holders
```

The bulk transfer respects `config('ticketing.transfers.bulk_max_size')` and fails if any requested pass ID is outside the current owner scope.

## Pass State Transitions

States and allowed transitions:

| Current State | Can transition to |
|---------------|-------------------|
| `Pending` | `Issued`, `Cancelled` |
| `Issued` | `Activated`, `Cancelled`, `Voided`, `Expired` |
| `Activated` | `Used`, `Voided`, `Expired` |
| `Used` | *(terminal — no transitions)* |
| `Cancelled` | *(terminal — no transitions)* |
| `Revoked` | *(terminal — no transitions)* |
| `Voided` | *(terminal — no transitions)* |
| `Expired` | *(terminal — no transitions)* |

### Transition Actions

```php
use AIArmada\Ticketing\Actions\ActivatePassAction;
use AIArmada\Ticketing\Actions\UsePassAction;
use AIArmada\Ticketing\Actions\CancelPassAction;
use AIArmada\Ticketing\Actions\RevokePassAction;
use AIArmada\Ticketing\Actions\VoidPassAction;
use AIArmada\Ticketing\Actions\ExpirePassAction;

// Activate at entry
app(ActivatePassAction::class)->handle($pass, scannedBy: $staffUser);

// Mark as used
app(UsePassAction::class)->handle($pass);

// Cancel before issuance
app(CancelPassAction::class)->handle($pass, reason: 'Order refunded');

// Revoke for policy violation
app(RevokePassAction::class)->handle($pass, reason: 'Fraud detected');

// Void (admin action)
app(VoidPassAction::class)->handle($pass, reason: 'Duplicate issuance');

// Expire (scheduled task)
app(ExpirePassAction::class)->handle($pass);
```

## Pass Delivery

The package dispatches `PassIssued` and `PassTransferred` events. When `aiarmada/orders` is installed, passes are automatically issued when an order transitions to `paid`.

To send passes via email, implement `PassDeliveryService`:

```php
use AIArmada\Ticketing\Contracts\PassDeliveryService;
use AIArmada\Ticketing\Models\Pass;
use Illuminate\Support\Facades\Mail;

class EmailPassDeliveryService implements PassDeliveryService
{
    public function deliver(Pass $pass): void
    {
        if ($pass->holder_email === null) {
            return;
        }

        Mail::to($pass->holder_email)->send(new TicketMail($pass));
    }
}
```

Bind your implementation:

```php
// AppServiceProvider
use AIArmada\Ticketing\Contracts\PassDeliveryService;

public function boot(): void
{
    $this->app->bind(PassDeliveryService::class, EmailPassDeliveryService::class);
}
```

## Owner Scoping

Passes use the `HasOwner` trait. Ticket types inherit owner boundaries from their ticketable model, and pass holders/transfers inherit from their pass:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

// Owner-scoped queries (default)
$passes = Pass::all(); // Only current owner's passes

// For specific owner
$passes = Pass::forOwner($owner)->get();

// Include global records
$passes = Pass::forOwner($owner, includeGlobal: true)->get();

// Global-only records
$passes = Pass::globalOnly()->get();

// Explicit owner context
OwnerContext::withOwner($owner, function () use ($pass) {
    app(TransferPassToHolderAction::class)->handle(
        pass: $pass,
        newHolder: $newHolder,
        reason: 'Corporate transfer',
    );
});

// Bypass owner scoping (use with caution)
$allPasses = Pass::withoutOwnerScope()->get();
```

## Events

| Event | Payload | Description |
|-------|---------|-------------|
| `PassIssued` | `$pass` | Fired when a pass is issued |
| `PassTransferred` | `$pass, $oldHolder, $newHolder` | Fired after transfer completes |

Listen to events as usual:

```php
use AIArmada\Ticketing\Events\PassTransferred;
use Illuminate\Support\Facades\Event;

Event::listen(function (PassTransferred $event): void {
    Log::info("Pass {$event->pass->pass_no} transferred");
});
```

## Read next

- [Configuration](03-configuration.md) — Review configuration options
- [Troubleshooting](99-troubleshooting.md) — Debug common issues
