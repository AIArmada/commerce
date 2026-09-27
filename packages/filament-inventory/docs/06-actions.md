---
title: Actions
---

# Actions

The package provides 8 table and page actions for inventory operations.

## Stock Operations

### Receive Stock

Add inventory to a location (purchase receipts, returns, production output).

```php
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;

ReceiveStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Receiving location (owner-scoped) |
| quantity | TextInput (numeric) | Quantity to receive |
| purchase_order | TextInput | PO number |
| supplier | TextInput | Supplier name |
| received_at | DatePicker | Received date |
| notes | Textarea | Notes |

The action runs against the current record as the inventoryable model. The location is revalidated server-side; the reason is built from PO/supplier.

**Events Dispatched:**
- Creates `receipt` movement
- Updates stock level
- May trigger allocation fulfillment

### Ship Stock

Remove inventory from a location (sales, transfers out, consumption).

```php
use AIArmada\FilamentInventory\Actions\ShipStockAction;

ShipStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Shipping location (owner-scoped) |
| quantity | TextInput (numeric) | Quantity to ship |
| order_number | TextInput | Order number |
| customer | TextInput | Customer name |
| tracking_number | TextInput | Tracking number |
| shipped_at | DatePicker | Ship date |
| notes | Textarea | Notes |

**Validation:**
- Quantity must be ≥ 1; insufficient stock surfaces as a danger notification via `InsufficientInventoryException`

**Events Dispatched:**
- Creates `shipment` movement
- Updates stock level

### Transfer Stock

Move inventory between locations.

```php
use AIArmada\FilamentInventory\Actions\TransferStockAction;

TransferStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| from_location_id | Select | Source location |
| to_location_id | Select | Destination location |
| quantity | TextInput (numeric) | Quantity to transfer |
| notes | Textarea | Notes |

**Behavior:**
- Creates paired movements (out/in)
- Changing the source resets the destination and excludes it from destination options
- Cannot transfer to same location

### Adjust Stock

Correct inventory discrepancies (cycle count corrections, damage write-offs).

```php
use AIArmada\FilamentInventory\Actions\AdjustStockAction;

AdjustStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Location |
| new_quantity | TextInput (numeric) | New absolute quantity |
| reason | Select | cycle_count, damaged, expired, lost, found, correction (default), initial_stock, other |
| notes | Textarea | Notes |

**Validation:**
- Sets the stock to an absolute count (not a delta)
- Reason is mandatory

**Events Dispatched:**
- Creates `adjustment` movement

## Cycle Count

Physical inventory verification workflow.

```php
use AIArmada\FilamentInventory\Actions\CycleCountAction;

CycleCountAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Location |
| system_quantity | TextInput (numeric) | Current system quantity (auto-filled) |
| counted_quantity | TextInput (numeric) | Physical count result |
| counter | TextInput | Counted by |

**Behavior:**
- Displays current system quantity
- Auto-calculates variance
- Creates adjustment if variance ≠ 0
- Updates inventory accuracy metrics

## Allocation Management

### Release Allocation

Cancel or release reserved inventory.

```php
use AIArmada\FilamentInventory\Actions\ReleaseAllocationAction;

ReleaseAllocationAction::make()
```

The action has no form fields; it shows a confirmation modal and releases the full allocation record.

**Behavior:**
- Deletes the allocation and restores its quantity to available stock
- Dispatches `InventoryReleased`

## Reorder Management

### Approve Reorder Suggestion

Accept system-generated reorder recommendation.

```php
use AIArmada\FilamentInventory\Actions\ApproveReorderSuggestionAction;

ApproveReorderSuggestionAction::make()
```

**Behavior:**
- Marks suggestion as `approved`
- Typically triggers procurement workflow
- Success notification displayed

### Reject Reorder Suggestion

Dismiss a reorder recommendation.

```php
use AIArmada\FilamentInventory\Actions\RejectReorderSuggestionAction;

RejectReorderSuggestionAction::make()
```

**Behavior:**
- Marks suggestion as `rejected`
- Danger-colored button
- Removes from pending list

## Using Actions Programmatically

All actions use the underlying inventory package services:

```php
use AIArmada\Inventory\Actions\ReceiveInventory;

ReceiveInventory::run(
    model: $product,
    locationId: $location->id,
    quantity: 100,
    reason: 'PO-001',
    note: 'Initial stock',
);
```

## Action Authorization

Actions respect Filament's authorization system via the following policies:

| Policy | Model |
|--------|-------|
| `InventoryAllocationPolicy` | `InventoryAllocation` |
| `InventoryLevelPolicy` | `InventoryLevel` |
| `InventoryReorderSuggestionPolicy` | `InventoryReorderSuggestion` |

```php
// In your policy
public function receiveStock(User $user, InventoryLocation $location): bool
{
    return $user->can('manage inventory');
}
```

## Customizing Actions

The shipped action classes are `final` and expose no `afterReceive`-style hooks. Build your own `Action::make(...)` copying the field pattern above and calling the core `aiarmada/inventory` action inside `->action()`:

```php
use AIArmada\Inventory\Actions\ReceiveInventory;
use Filament\Actions\Action;

Action::make('receive_stock')
    ->label('Receive Stock')
    ->form([...])
    ->action(function (Model $record, array $data): void {
        ReceiveInventory::run($record, $data['location_id'], (int) $data['quantity']);

        Notification::make()->title('Stock received')->success()->send();
    });
```

## Multitenancy

All actions automatically scope to the current owner via the core inventory package's owner-aware actions.
