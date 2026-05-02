---
title: Resources (Legacy)
---

# Resources

The Filament J&T Express plugin provides three read-only resources for monitoring shipping activity.

## JntOrderResource

Displays J&T Express shipping orders.

### Table Columns

| Column | Description |
|--------|-------------|
| Order ID | Your order reference |
| Tracking Number | J&T waybill number |
| Customer Code | J&T customer account |
| Last Status | Current shipping status |
| Created At | Order creation timestamp |

### Infolist Sections

- **Order Information** – IDs, tracking, customer code
- **Status** – current status and timestamps
- **Sender Details** – sender name, phone, address
- **Receiver Details** – receiver name, phone, address
- **Package Details** – weight, dimensions, declared value

### Global Search

Searchable by: `order_id`, `tracking_number`, `customer_code`, `last_status`

---

## JntTrackingEventResource

Displays tracking events received from J&T webhooks.

### Table Columns

| Column | Description |
|--------|-------------|
| Tracking Number | J&T waybill number |
| Order Reference | Your order reference |
| Scan Type | Event type (pickup, transit, delivery) |
| Description | Event description |
| Scanned At | Event timestamp |

### Infolist Sections

- **Event Information** – tracking number, order reference
- **Scan Details** – type, description, location
- **Timestamps** – scanned at, created at

### Global Search

Searchable by: `tracking_number`, `order_reference`, `scan_type_name`, `description`

---

## JntWebhookLogResource

Displays incoming webhook notifications for debugging.

### Table Columns

| Column | Description |
|--------|-------------|
| Tracking Number | J&T waybill number |
| Order Reference | Your order reference |
| Status | Processing status (pending/processed/failed) |
| Received At | Webhook receipt timestamp |

### Infolist Sections

- **Webhook Information** – tracking, order reference, status
- **Payload** – raw JSON data received
- **Processing** – status, error message if any

### Global Search

Searchable by: `tracking_number`, `order_reference`, `processing_status`

---

## Read-Only Design

All resources are read-only by design:

- **No create/edit pages** – orders are created via the J&T API
- **No delete actions** – preserves audit trail
- **View-only infolists** – safe data inspection

To create or modify orders, use the `aiarmada/jnt` package directly:

```php
use AIArmada\Jnt\Facades\JntExpress;

// Create order via API
$order = JntExpress::createOrderFromArray($orderData);

// Cancel order via API
JntExpress::cancelOrder($orderId, $reason);
```

---

## Extending Resources

### Custom Table Columns

Create a custom resource extending the base:

```php
namespace App\Filament\Resources;

use AIArmada\FilamentJnt\Resources\JntOrderResource as BaseResource;
use Filament\Tables\Table;

class JntOrderResource extends BaseResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                // Add custom columns
            ]);
    }
}
```

### Custom Navigation

Override navigation settings per-resource:

```php
protected static ?string $navigationGroup = 'Custom Group';
protected static ?int $navigationSort = 5;
```

---

## BaseJntResource

All resources extend `BaseJntResource` which provides:

- Consistent navigation grouping
- Badge color configuration
- Navigation sort order from config
- Shared base functionality

```php
use AIArmada\FilamentJnt\Resources\BaseJntResource;

class CustomResource extends BaseJntResource
{
    // Inherits navigation group, badge color, etc.
}
```
