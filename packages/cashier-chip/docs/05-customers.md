---
title: Customer Management
---

# Customer Management

Cashier CHIP manages customers through CHIP's Client API. Each billable model is linked to a CHIP client ID.

## Creating Customers

### Create a CHIP Customer

```php
// Create a new CHIP customer
$user->createAsChipCustomer();

// Create with additional options
$user->createAsChipCustomer([
    'phone' => '+60123456789',
    'street_address' => '123 Main Street',
    'city' => 'Kuala Lumpur',
    'country' => 'MY',
]);
```

### Create or Get Existing Customer

```php
// Create a client if needed, otherwise reuse the linked one
$user->createOrGetChipCustomer();
```

### Auto-Creation

Customers are automatically created when you:

- Create a checkout session
- Create a subscription
- Charge the customer

## Checking Customer Status

```php
// Get the CHIP client ID
$chipId = $user->chipId();

// Check if user has a CHIP ID
if ($user->hasChipId()) {
    // User is a CHIP customer
}
```

## Updating Customers

```php
// Update customer information in CHIP
$user->updateChipCustomer([
    'full_name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+60123456789',
]);
```

## Syncing Customer Data

### Sync to CHIP

```php
// Sync the current billable details to CHIP
$user->syncChipCustomerDetails();

// Or create the customer first when needed
$user->syncOrCreateChipCustomer();
```

This syncs the following fields (if present on your model):
- `chipName()` → `full_name`
- `chipEmail()` → `email`
- `chipPhone()` → `phone`
- `chipCountry()` → `country`
- `chipAddress()` → address fields

### Custom Sync Mapping

Override the CHIP mapping helpers for custom data:

```php
class User extends Authenticatable
{
    use Billable;

    public function chipName(): ?string
    {
        return $this->full_name;
    }

    public function chipPhone(): ?string
    {
        return $this->phone_number;
    }

    public function chipAddress(): array
    {
        return [
            'street_address' => $this->line1,
            'city' => $this->city,
            'country' => 'MY',
        ];
    }
}
```

## Retrieving Customer from CHIP

```php
// Get the full CHIP client object
$client = $user->asChipCustomer();

// Access client properties
echo $client->full_name;
echo $client->email;
```

## Customer Balance

CHIP doesn't support customer credit balances like Stripe. For credit functionality, use vouchers or implement a local credit system.

## Multiple Customer Models

You can use different models for billing:

```php
// In a service provider
use AIArmada\CashierChip\Cashier;

public function boot(): void
{
    Cashier::useCustomerModel(Team::class);
}
```

The model must:
1. Use the `Billable` trait
2. Have a factory for testing

```php
class Team extends Model
{
    use Billable;
    
    protected static function newFactory()
    {
        return TeamFactory::new();
    }
}
```

## Database Schema

The `chip_customers` table is owned by `aiarmada/chip` and stores the relationship:

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `subject_id` | uuid | Morph key for the local billable subject |
| `subject_type` | string | Morph type for the local billable subject |
| `chip_customer_id` | string | CHIP client ID |
| `owner_id` | uuid nullable | Owner scope morph key when multitenancy is enabled |
| `owner_type` | string nullable | Owner scope morph type when multitenancy is enabled |
| `metadata` | json nullable | Bridge metadata |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
