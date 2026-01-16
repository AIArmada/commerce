---
title: Usage
---

# Usage Guide

## Order Resource

### Accessing Orders

Navigate to `/admin/orders` to view the order list.

### Order List Features

- **Search**: Search by order number, customer name, or email
- **Filters**: Filter by status, date range, payment status
- **Sorting**: Sort by any column
- **Quick Actions**: View, edit, or delete orders

### Viewing an Order

Click on an order to see:

- **Order Details**: Order number, status, timestamps
- **Customer Information**: Name, email, contact details
- **Items Tab**: Line items with quantities and prices
- **Payments Tab**: Payment history and status
- **Notes Tab**: Internal and customer-visible notes

### Header Actions

On the order view page, header actions are contextual based on order state:

| Action | Visible When | Description |
|--------|--------------|-------------|
| Confirm Payment | `PendingPayment` state | Record payment details |
| Ship Order | `Processing` state | Enter carrier and tracking |
| Confirm Delivery | `Shipped` state | Mark as delivered |
| Cancel Order | Cancelable states | Cancel with reason |
| Download Invoice | Paid orders | Download PDF invoice |

## Dashboard Widgets

### Order Stats Widget

Shows key metrics:
- Today's Orders (with % change from yesterday)
- Today's Revenue (with % change)
- Pending Orders (requiring action)
- Monthly Revenue (with % change from last month)

### Order Status Distribution Widget

Doughnut chart showing orders by status.

### Recent Orders Widget

Table of the 10 most recent orders with quick view action.

### Order Timeline Widget

Available on the order view page. Shows chronological history:
- Order creation
- Payment events
- Status changes
- Shipment events
- Notes added

Includes a form to add new notes directly from the timeline.

## Relation Managers

### Items Relation Manager

Read-only view of order items:
- Product name and SKU
- Quantity and unit price
- Discounts and taxes
- Line totals

### Payments Relation Manager

View payment records:
- Gateway and transaction ID
- Amount and currency
- Payment status with color badges
- Payment timestamp

### Notes Relation Manager

Manage order notes:
- **Create**: Add new notes
- **Toggle Visibility**: Mark notes as customer-visible
- **Edit/Delete**: Modify existing notes

## Keyboard Shortcuts

Standard Filament keyboard shortcuts apply:
- `Ctrl/Cmd + K`: Open global search
- `Ctrl/Cmd + S`: Save current form

## Bulk Actions

On the order list:
- **Delete Selected**: Delete multiple orders (with confirmation)

## Best Practices

### Regular Monitoring

1. Review Order Stats dashboard for trends
2. Process orders in the correct workflow sequence
3. Use the timeline widget to track order history

### Note Usage

- Use internal notes for team communication
- Use customer-visible notes for updates the customer should see
- Notes appear in the order timeline

### Invoice Downloads

- Invoices are generated on-demand
- Requires `spatie/laravel-pdf` to be configured
- Customize invoice template in `resources/views/vendor/orders`
