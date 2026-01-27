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

- Finds or creates customer record
- Associates customer with session
- Validates customer eligibility

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

- Queues invoice generation
- Triggers receipt creation
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
    public function execute(CheckoutSession $session): StepResult
    {
        // Your validation logic
        if ($this->isValid($session)) {
            return StepResult::success(
                $this->identifier(),
                'Custom validation passed',
                ['validated_at' => now()]
            );
        }

        return StepResult::failed(
            $this->identifier(),
            'Custom validation failed',
            ['reason' => 'Some validation error']
        );
    }

    public function rollback(CheckoutSession $session): void
    {
        // Undo any changes if needed
    }

    public function identifier(): string
    {
        return 'custom_validation';
    }

    public function dependencies(): array
    {
        return ['validate_cart']; // Must run after validate_cart
    }

    public function shouldExecute(CheckoutSession $session): bool
    {
        return true; // Or conditional logic
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
public function dependencies(): array
{
    return ['validate_cart', 'resolve_customer'];
}
```

The registry ensures dependencies execute before the step.

## Conditional Execution

Steps can skip execution based on conditions:

```php
public function shouldExecute(CheckoutSession $session): bool
{
    // Only for physical products
    return $session->cart->hasPhysicalItems();
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
// Before step execution
CheckoutStepStarted::class

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
