---
title: Usage
---

# Usage

## Resources

### Shipment Resource

The shipment resource provides full CRUD operations for shipments.

#### Creating a Shipment

1. Navigate to **Shipping → Shipments**
2. Click **New Shipment**
3. Fill in the form:
   - **Carrier**: Select shipping carrier
   - **Service**: Select service level
   - **Origin Address**: Sender details
   - **Destination Address**: Recipient details
   - **Package Details**: Weight, dimensions
4. Click **Create**

The shipment is created in **Draft** status.

#### Shipping a Shipment

1. Open a shipment in Draft or Pending status
2. Click the **Ship** action button
3. Confirm carrier selection
4. The shipment transitions to **Shipped** status
5. If the carrier supports it, a tracking number and label are generated

#### Tracking Updates

1. Open a shipment
2. Click **Sync Tracking** to fetch latest status
3. View tracking events in the **Events** tab

#### Cancelling a Shipment

1. Open a shipment in Draft or Pending status
2. Click **Cancel**
3. Confirm cancellation
4. Shipment transitions to **Cancelled** status

### Shipping Zone Resource

Manage geographic zones for rate calculation.

#### Creating a Zone

1. Navigate to **Shipping → Shipping Zones**
2. Click **New Shipping Zone**
3. Configure:
   - **Name**: Zone identifier
   - **Type**: Country, State, Postcode, or Radius
   - **Countries**: ISO country codes
   - **States/Postcodes**: Based on type selection
4. Click **Create**

#### Adding Rates

1. Open a shipping zone
2. Go to the **Rates** tab
3. Click **Add Rate**
4. Configure:
   - **Name**: Rate display name
   - **Rate Type**: Flat, Per KG, Per Item, or Percentage
   - **Base Rate**: Base shipping cost
   - **Weight Limits**: Min/max weight range
   - **Delivery Time**: Estimated days
5. Click **Create**

### Return Authorization Resource

Manage return merchandise authorizations (RMAs).

#### Processing a Return

1. Navigate to **Shipping → Return Authorizations**
2. Open a pending return
3. Review the return details and reason
4. Click **Approve** or **Reject**
5. If approved, a return shipping label can be generated

## Pages

### Shipping Dashboard

The dashboard provides an overview of shipping operations:

- **Pending Shipments**: Count awaiting shipping
- **In Transit**: Currently shipping
- **Delivered Today**: Successful deliveries
- **Exceptions**: Issues requiring attention
- **Pending Returns**: RMAs awaiting approval

Access via **Shipping → Dashboard** or configure as the default landing page.

### Fulfillment Queue

Shows orders ready for shipping (requires Orders package):

1. Navigate to **Shipping → Fulfillment Queue**
2. View orders in "Processing" status
3. For each order:
   - Click **Ship**
   - Select carrier
   - Enter tracking number
   - Confirm

Orders older than the configured threshold are highlighted as urgent.

#### Filtering

- **Older than 24h**: Show only old orders
- **Urgent (>48h)**: Show orders needing immediate attention
- **Shipping Method**: Filter by shipping method

### Manifest Page

Generate daily shipping manifests for carrier pickup:

1. Navigate to **Shipping → Manifest**
2. Select date and carrier
3. Click **Generate Manifest**
4. Print or download the manifest
5. Mark as picked up when carrier collects packages

## Actions

### Single Record Actions

Available on individual shipment records:

| Action | Description | Visible When |
|--------|-------------|--------------|
| Ship | Create shipment with carrier | Draft, Pending |
| Cancel | Cancel the shipment | Draft, Pending |
| Print Label | Download shipping label | Has label |
| Sync Tracking | Update tracking from carrier | Has tracking number |

### Bulk Actions

Available on the shipment list:

| Action | Description |
|--------|-------------|
| Bulk Ship | Ship multiple shipments |
| Bulk Cancel | Cancel multiple shipments |
| Bulk Print Labels | Download labels as ZIP |
| Bulk Sync Tracking | Update tracking for multiple |

### Using Actions Programmatically

```php
use AIArmada\FilamentShipping\Actions\ShipAction;
use AIArmada\FilamentShipping\Actions\CancelShipmentAction;

// In a resource
public static function table(Table $table): Table
{
    return $table
        ->actions([
            ShipAction::make(),
            CancelShipmentAction::make(),
        ]);
}
```

## Widgets

### Adding to Dashboard

Widgets are automatically registered. To add to a custom page:

```php
use AIArmada\FilamentShipping\Widgets\ShippingDashboardWidget;
use AIArmada\FilamentShipping\Widgets\PendingShipmentsWidget;

protected function getHeaderWidgets(): array
{
    return [
        ShippingDashboardWidget::class,
        PendingShipmentsWidget::class,
    ];
}
```

### Widget Permissions

Widgets respect the same permissions as resources. Users need `view_any_shipment` permission to see shipping widgets.

## Customization

### Extending Resources

Create your own resource extending the base:

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentShipping\Resources\ShipmentResource as BaseResource;

class ShipmentResource extends BaseResource
{
    // Add custom columns
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->columns([
                ...parent::table($table)->getColumns(),
                Tables\Columns\TextColumn::make('custom_field'),
            ]);
    }
}
```

### Custom Actions

```php
use Filament\Actions\Action;

Action::make('customShipAction')
    ->label('Special Ship')
    ->icon('heroicon-o-rocket')
    ->action(function (Shipment $record) {
        // Custom shipping logic
    });
```

### Overriding Views

Publish and modify views:

```bash
php artisan vendor:publish --tag=filament-shipping-views
```

Views are published to `resources/views/vendor/filament-shipping/`.
