---
title: Usage
---

# Usage

## Price List Resource

The Price List Resource provides full CRUD operations for managing price lists.

### List View

The table displays:

| Column | Description |
|--------|-------------|
| Name | Searchable, sortable |
| Currency | Badge display |
| Prices | Count of prices in list |
| Priority | Numeric, sortable |
| Default | Boolean icon |
| Active | Boolean icon |
| Starts | Date (toggleable) |
| Ends | Date (toggleable) |

**Filters**:
- Active status (ternary)
- Default status (ternary)

**Actions**:
- View
- Edit
- Bulk Delete

### Create/Edit Form

The form is organized into sections:

**Price List Details**:
- Name (auto-generates slug)
- Slug (unique)
- Currency select
- Description (full width)

**Scheduling**:
- Start Date (datetime picker)
- End Date (datetime picker)

**Settings** (sidebar):
- Active toggle
- Default Price List toggle
- Priority number

### Relation Managers

When viewing/editing a price list, two relation managers are available:

#### Prices Relation Manager

Manage individual prices within the price list:

**Form Fields**:
- Type (Product/Variant select)
- Product/Variant searchable select
- Price amount (cents)
- Compare price (cents, optional)
- Minimum quantity
- Currency
- Start/End dates

**Table Columns**:
- Type (class basename)
- Product name
- Price (formatted as money)
- Compare price
- Min Qty
- Start/End dates

#### Tiers Relation Manager

Configure quantity-based tiered pricing:

**Form Sections**:

*Tier Configuration*:
- Apply To (Product/Variant)
- Product/Variant select
- Min/Max quantity
- Range preview

*Pricing*:
- Amount (cents)
- Discount type (percentage/fixed)
- Discount value
- Currency

**Table Columns**:
- Type
- Item name
- Quantity range (badge)
- Pricing type (badge)
- Amount
- Discount value

## Promotion Resource

Manage promotional discounts (requires `aiarmada/promotions`).

### List View

| Column | Description |
|--------|-------------|
| Promotion | Name with code description |
| Type | Badge with color |
| Discount | Formatted based on type |
| Uses | Count with limit display |
| Active | Boolean icon |
| Starts/Ends | Date display |

**Filters**:
- Type select
- Active status

**Actions**:
- View
- Edit
- Duplicate (creates copy with cleared code/usage)

### Create/Edit Form

**Promotion Details**:
- Name
- Coupon Code (optional, unique)
- Description

**Discount**:
- Discount Type (percentage, fixed, etc.)
- Discount Value (label changes based on type)
- Minimum Purchase (cents)
- Minimum Quantity

**Scheduling**:
- Start Date
- End Date

**Settings** (sidebar):
- Active toggle
- Stackable toggle
- Priority number

**Usage Limits** (sidebar):
- Total Uses limit
- Per Customer limit
- Usage count display

## Price Simulator

Interactive tool to test price calculations (requires `aiarmada/products`).

### Input Parameters

- **Product Type**: Product or Variant selector
- **Product/Variant**: Searchable select with price display
- **Customer**: Optional customer select (if customers package installed)
- **Quantity**: Numeric input (default 1)
- **Effective Date**: Datetime picker for future pricing

### Actions

- **Calculate Price**: Runs price calculation
- **Clear**: Resets form and results

### Results Display

**Price Calculation Result**:
- Original Price (per unit)
- Final Price (per unit, green)
- Discount (per unit, red)
- Quantity
- Total Price (large, green)

**Applied Pricing Rules** (if any applied):
- Price List (info badge)
- Promotion (warning badge)
- Price Tier (success badge)
- Discount Percentage
- Discount Source

**Breakdown** (collapsible):
- Step-by-step calculation details

## Pricing Settings Page

Configure pricing defaults and features.

### Available Settings

**Currency & Display**:
- Default Currency (select)
- Decimal Places (0-4)
- Rounding Mode (up, down, half_up, half_down)

**Tax**:
- Prices Include Tax (toggle)

**Order Limits**:
- Minimum Order Value (cents)
- Maximum Order Value (cents)

**Features**:
- Promotional Pricing (toggle)
- Tiered Pricing (toggle)
- Customer Group Pricing (toggle)

### Saving

Click the "Save" button in the header to persist settings.

## Stats Widget

Dashboard widget showing pricing overview.

**Statistics Displayed**:
- Active Price Lists (count with icon)
- Active Promotions (count, if promotions installed)
- Promotion Uses (total redemptions, if promotions installed)

Widget auto-refreshes every 30 seconds.

## Example Workflows

### Creating a Wholesale Price List

1. Navigate to **Pricing > Price Lists**
2. Click **Create**
3. Enter details:
   - Name: "Wholesale"
   - Slug: "wholesale" (auto-filled)
   - Currency: MYR
   - Priority: 10
   - Toggle: Active ✓
4. Save the price list
5. In the **Prices** tab, add prices for products
6. In the **Tiers** tab, configure volume discounts

### Testing Price Calculation

1. Navigate to **Pricing > Price Simulator**
2. Select a product
3. Optionally select a customer
4. Enter quantity (e.g., 50)
5. Click **Calculate Price**
6. Review the breakdown to understand applied rules

### Setting Up a Time-Limited Promotion

1. Navigate to **Pricing > Promotions**
2. Click **Create**
3. Enter details:
   - Name: "Weekend Sale"
   - Type: Percentage
   - Value: 15
4. Set schedule:
   - Starts: Friday 00:00
   - Ends: Sunday 23:59
5. Set limits:
   - Usage Limit: 100
6. Save and activate
