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
| location_id | Select | Receiving location (owner-scoped, searchable) |
| quantity | TextInput (numeric) | Quantity received, min 1 |
| purchase_order | TextInput | PO number |
| supplier | TextInput | Supplier name |
| received_at | DatePicker | Movement date, defaults to now |
| notes | Textarea | Quality notes, inspection results |

**Behavior:**
- Creates a `receipt` movement
- `purchase_order` and `supplier` are joined into the movement `reason`
- Rejects a location outside the current owner scope

### Ship Stock

Remove inventory from a location (sales, transfers out, consumption).

```php
use AIArmada\FilamentInventory\Actions\ShipStockAction;

ShipStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Shipping location (owner-scoped, searchable) |
| quantity | TextInput (numeric) | Quantity to ship, min 1 |
| order_number | TextInput | Order number |
| customer | TextInput | Customer name |
| tracking_number | TextInput | Carrier tracking number |
| shipped_at | DatePicker | Movement date, defaults to now |
| notes | Textarea | Shipping notes, special instructions |

**Validation:**
- Throws `InsufficientInventoryException` on overship, surfaced as a danger notification

**Behavior:**
- Creates a `shipment` movement
- `order_number`, `customer` and `tracking_number` are joined into the movement `reason`
- Rejects a location outside the current owner scope

### Transfer Stock

Move inventory between locations.

```php
use AIArmada\FilamentInventory\Actions\TransferStockAction;

TransferStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| from_location_id | Select | Source location (owner-scoped, searchable) |
| to_location_id | Select | Destination location, reset when source changes |
| quantity | TextInput (numeric) | Quantity to transfer |
| notes | Textarea | Transfer notes |

**Behavior:**
- Creates paired movements (out/in)
- Both locations are validated against the current owner scope

### Adjust Stock

Correct inventory discrepancies (cycle count corrections, damage write-offs).

```php
use AIArmada\FilamentInventory\Actions\AdjustStockAction;

AdjustStockAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Location (owner-scoped, searchable) |
| new_quantity | TextInput (numeric) | Absolute stock level to set, min 0 |
| reason | Select | `cycle_count`, `damaged`, `expired`, `lost`, `found`, `correction`, `initial_stock`, `other` |
| notes | Textarea | Optional notes |

**Validation:**
- `location_id`, `new_quantity` and `reason` are required
- `reason` defaults to `correction`

**Behavior:**
- Creates an `adjustment` movement
- Rejects a location outside the current owner scope

## Cycle Count

Physical inventory verification workflow.

```php
use AIArmada\FilamentInventory\Actions\CycleCountAction;

CycleCountAction::make()
```

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| location_id | Select | Location being counted; live, auto-fills `system_quantity` |
| system_quantity | TextInput (numeric) | Disabled, prefilled from `InventoryLevel` |
| counted_quantity | TextInput (numeric) | Physical count result, autofocus |
| counter | TextInput | Who performed the count |

**Behavior:**
- Selecting a location populates the current system quantity
- Creating an adjustment if the variance is non-zero
- Rejects a location outside the current owner scope

## Allocation Management

### Release Allocation

Cancel or release reserved inventory.

```php
use AIArmada\FilamentInventory\Actions\ReleaseAllocationAction;

ReleaseAllocationAction::make()
```

**Fields:**

None. The action is a confirmation-only record action that releases the whole
allocation.

**Behavior:**
- Calls `ReleaseStock::make()->releaseAllocation($record)` and releases the full allocated quantity
- A non-positive return (already released, or outside the owner scope) surfaces a danger notification
- Danger-colored, `requiresConfirmation()`

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

Every Filament action delegates to an `AIArmada\Inventory\Actions\*` action that
exposes `run()` through the `AsAction` trait:

```php
use AIArmada\Inventory\Actions\ReceiveInventory;

ReceiveInventory::run(
    model: $product,            // the inventoryable record
    locationId: $location->id,
    quantity: 100,
    reason: 'PO-001',
    note: 'Initial stock',
    userId: auth()->id(),
    occurredAt: now(),
);
```

## Action Authorization

Actions respect Filament's authorization system via the following policies:

| Policy | Model |
|--------|-------|
| `InventoryAllocationPolicy` | `InventoryAllocation` |
| `InventoryLevelPolicy` | `InventoryLevel` |
| `InventoryReorderSuggestionPolicy` | `InventoryReorderSuggestion` |

Each policy only declares the standard CRUD set (`viewAny`, `view`, `create`,
`update`, `delete`) — there are no per-operation abilities such as
`receiveStock`. Customise those methods to gate stock operations.

## Customizing Actions

The packaged actions are `final` factories: `XXXAction::make(string $name = '...')`
returns a configured `Filament\Actions\Action`, so they cannot be extended. Compose
your own action instead:

```php
use AIArmada\Inventory\Actions\ReceiveInventory;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

Action::make('receive_stock')
    ->form([
        TextInput::make('location_id')->required(),
        TextInput::make('quantity')->numeric()->required(),
    ])
    ->action(function (Model $record, array $data): void {
        ReceiveInventory::run(
            model: $record,
            locationId: $data['location_id'],
            quantity: (int) $data['quantity'],
        );

        Notification::make()->title('Stock received')->success()->send();
    });
```

## Multitenancy

All actions automatically scope to the current owner via the core inventory package's owner-aware actions.
