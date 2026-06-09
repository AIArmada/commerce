---
title: Checkout Steps
---

# Checkout Steps

The checkout process is divided into modular steps that can be customized, reordered, or replaced.

## Built-in Steps

### ValidateCartStep

Validates the cart before checkout:

- Ensures cart exists and has items
- Validates item availability
- Checks purchasable constraints

### ResolveCustomerStep

Resolves customer information:

- Resolves existing customer or billable subjects before payment
- Associates any existing subject with the checkout session
- Leaves direct-capable guest flows side-effect free before payment

### PersistCustomerStep

Persists customer information after payment succeeds:

- Creates or syncs the customer record from checkout payload data
- Merges or promotes guest customers when an authenticated actor is known
- Updates the checkout session before `CreateOrderStep` runs

### CalculatePricingStep

Calculates item and cart totals:

- Applies price list rules
- Calculates line item totals
- Computes subtotals

### ApplyDiscountsStep

Applies promotions and vouchers:

- Evaluates promotion rules
- Applies voucher codes
- Calculates discount amounts

### CalculateTaxStep

Computes applicable taxes:

- Determines tax zone
- Applies tax rates
- Calculates tax totals

### CalculateShippingStep

Calculates shipping costs:

- Evaluates shipping methods
- Calculates shipping rates
- Updates session with costs

### ReserveInventoryStep

Reserves inventory for items:

- Creates stock reservations
- Sets reservation expiry
- Handles reservation failures
- Runs before `process_payment` by default and moves to the start of the post-payment phase when `integrations.inventory.reserve_before_payment` is `false`

### ProcessPaymentStep

Processes the payment:

- Creates payment request
- Calls payment gateway
- Handles payment result

### CreateOrderStep

Creates the order record:

- Generates order from session
- Creates order items
- Sets order addresses

### DispatchDocumentGenerationStep

Dispatches document generation:

- Queues invoice generation when checkout document generation is explicitly enabled
- Triggers receipt creation when checkout document generation is explicitly enabled
- Dispatches notifications

## Step Result

Each step returns a `StepResult`:

```php
use AIArmada\Checkout\Data\StepResult;

// Success
$result = StepResult::success('step_name', 'Step completed', ['key' => 'value']);

// Skipped
$result = StepResult::skipped('step_name', 'Conditions not met');

// Failed
$result = StepResult::failed('step_name', 'Error message', ['field' => 'error']);

// Check result
$result->isSuccessful(); // true for Completed or Skipped
$result->status;         // StepStatus enum
$result->data;           // Additional data
$result->errors;         // Validation errors
```

## Creating Custom Steps

### Step Interface

Implement `CheckoutStepInterface`:

```php
<?php

namespace App\Checkout\Steps;

use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;

class CustomValidationStep implements CheckoutStepInterface
{
    public function getIdentifier(): string
    {
        return 'custom_validation';
    }

    public function getName(): string
    {
        return 'Custom Validation';
    }

    public function validate(CheckoutSession $session): array
    {
        // Return validation errors keyed by field, or an empty array to proceed.
        return [];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        // Your validation logic
        if ($this->isValid($session)) {
            return StepResult::success(
                $this->getIdentifier(),
                'Custom validation passed',
                ['validated_at' => now()]
            );
        }

        return StepResult::failed(
            $this->getIdentifier(),
            'Custom validation failed',
            ['reason' => 'Some validation error']
        );
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return false; // Or conditional logic
    }

    public function rollback(CheckoutSession $session): void
    {
        // Undo any changes if needed
    }

    public function getDependencies(): array
    {
        return ['validate_cart']; // Must run after validate_cart
    }

    private function isValid(CheckoutSession $session): bool
    {
        // Your validation logic
        return true;
    }
}
```

### Registering Custom Steps

Register in a service provider:

```php
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;

public function boot(): void
{
    $registry = app(CheckoutStepRegistryInterface::class);
    
    $registry->register('custom_validation', new CustomValidationStep());
}
```

Or via config:

```php
// config/checkout.php
'steps' => [
    'order' => [
        'validate_cart',
        'custom_validation', // Add your step
        'calculate_pricing',
        // ...
    ],
],
```

## Step Dependencies

Steps can declare dependencies:

```php
public function getDependencies(): array
{
    return ['validate_cart', 'resolve_customer'];
}
```

Checkout validates dependencies before executing a step, but the configured step order still determines the actual sequence. For the built-in inventory step, prefer `integrations.inventory.reserve_before_payment` over manually swapping `reserve_inventory` and `process_payment` in `steps.order`.

For built-in steps, `create_order` depends on `persist_customer`. Keep `persist_customer` enabled whenever `create_order` is enabled. Checkout now validates this configuration at boot and throws a `RuntimeException` if violated.

## Conditional Execution

Steps can skip execution based on conditions:

```php
public function canSkip(CheckoutSession $session): bool
{
    // Skip when there are no physical items.
    return ! $session->cart->hasPhysicalItems();
}
```

## Rollback Support

Implement rollback for reversible steps:

```php
public function rollback(CheckoutSession $session): void
{
    // Called when later steps fail
    // Undo this step's changes
    
    $reservation = $session->metadata['inventory_reservation'] ?? null;
    if ($reservation) {
        InventoryReservation::release($reservation);
    }
}
```

## Disabling Steps

Disable steps via config:

```php
'steps' => [
    'enabled' => [
        'reserve_inventory' => false, // Disable inventory
        'calculate_tax' => false,     // Disable tax
    ],
],
```

Or programmatically:

```php
$registry = app(CheckoutStepRegistryInterface::class);
$registry->disable('reserve_inventory');
```

## Reordering Steps

Change execution order via config:

```php
'steps' => [
    'order' => [
        'validate_cart',
        'calculate_tax',        // Tax before pricing
        'calculate_pricing',
        // ...
    ],
],
```

Or programmatically:

```php
$registry->setOrder([
    'validate_cart',
    'calculate_tax',
    'calculate_pricing',
    // ...
]);
```

## Step Events

Each step dispatches events:

```php
// After successful execution
CheckoutStepCompleted::class

// After failed execution
CheckoutStepFailed::class
```

Listen for specific steps:

```php
Event::listen(CheckoutStepCompleted::class, function ($event) {
    if ($event->stepIdentifier === 'create_order') {
        // Order was just created
    }
});
```

## Pipeline Runner

The step loop is extracted into `RunCheckoutPipeline`, which owns step iteration, skip rules, redirect exits, and failure exits. Both `CheckoutService::processCheckout()` and `ContinueFromStep()` delegate to this shared runner instead of duplicating the loop.

## Step Order Policy

Step ordering helpers (`resolveInventoryStepOrder`, `enforceStepDependencyOrder`) are isolated in `CheckoutStepOrderPolicy`, keeping ordering logic out of the service provider.

```php
use AIArmada\Checkout\Support\CheckoutStepOrderPolicy;

$policy = app(CheckoutStepOrderPolicy::class);
$normalizedOrder = $policy->normalizeInventoryStepOrder($registry, $registry->getOrder());
$registry->setOrder($normalizedOrder);
```
