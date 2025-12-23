---
title: Resources
---

# Resources

## DocResource

The primary resource for managing documents (invoices, receipts).

### List View

| Column | Description |
|--------|-------------|
| Number | Document number (searchable, copyable) |
| Type | Invoice or Receipt badge |
| Status | Color-coded status badge |
| Customer | Customer name (searchable) |
| Total | Formatted amount with currency |
| Issue Date | Document issue date |
| Due Date | Due date (red if overdue) |

#### Filters

- **Type** - Filter by invoice or receipt
- **Status** - Filter by document status
- **Overdue** - Show only overdue documents
- **Paid** - Show only paid documents
- **Has PDF** - Show documents with generated PDFs
- **This Month** - Show documents from current month

### Create/Edit Form

The form is organized into sections:

**Document Information**
- Document number (auto-generated if empty)
- Document type (invoice/receipt)
- Template selection
- Status, issue date, due date
- Currency and tax rate

**Customer Information**
- Name, email, phone
- Address, city, state, postcode, country

**Line Items**
- Repeatable items with name, quantity, price
- Optional description per item
- Collapsible, cloneable, reorderable

**Amounts**
- Subtotal, tax amount, discount, total
- Auto-calculated when items change

**Notes & Terms**
- Additional notes
- Terms and conditions

**Metadata**
- Custom key-value data

### View Page

Displays complete document details with:

- Document information infolist
- Customer address formatted
- Line items with calculated totals
- Amount summary
- Template information
- Timestamps

#### Header Actions

| Action | Description |
|--------|-------------|
| Edit | Open edit form |
| Generate PDF | Create/regenerate PDF |
| Download PDF | Download the PDF file |
| Mark as Sent | Update status (for draft/pending) |
| Mark as Paid | Record payment |
| Cancel | Cancel the document |
| Delete | Delete the document |

### Relation Manager

**StatusHistoriesRelationManager** displays:
- Status badge
- Notes/reason for change
- Who made the change
- Timestamp

---

## DocTemplateResource

Manage document templates with PDF configuration.

### List View

| Column | Description |
|--------|-------------|
| Name | Template name (searchable) |
| Slug | Unique identifier (copyable) |
| Type | Invoice or Receipt |
| Default | Boolean indicator |
| Documents | Count of documents using template |
| Updated | Last update time |

#### Filters

- **Document Type** - Filter by type
- **Default Only** - Show only default templates

### Create/Edit Form

**Template Information**
- Name (auto-generates slug)
- Slug (unique identifier)
- Description
- Document type
- View name (Blade view)
- Default template toggle

**PDF Settings**
- Paper format (A4, Letter, Legal, A3, A5)
- Orientation (Portrait/Landscape)
- Margins (top, right, bottom, left in mm)
- Print background toggle

**Custom Settings**
- Key-value pairs for custom configuration

### View Page

Displays:
- Template information
- PDF settings summary
- Custom settings (if any)
- Usage statistics (document count)
- Timestamps

#### Actions

| Action | Description |
|--------|-------------|
| Edit | Open edit form |
| Set as Default | Make default for document type |
| Delete | Delete the template |

---

## Extending Resources

### Custom Resource Class

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentDocs\Resources\DocResource as BaseDocResource;

class DocResource extends BaseDocResource
{
    public static function getNavigationGroup(): ?string
    {
        return 'Billing';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }
}
```

### Custom Table Configuration

```php
<?php

namespace App\Filament\Resources\DocResource\Tables;

use AIArmada\FilamentDocs\Resources\DocResource\Tables\DocsTable as BaseDocsTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocsTable extends BaseDocsTable
{
    public static function configure(Table $table): Table
    {
        return parent::configure($table)
            ->columns([
                // Add your custom columns
                TextColumn::make('custom_field'),
            ]);
    }
}
```

### Custom Form Fields

```php
<?php

namespace App\Filament\Resources\DocResource\Schemas;

use AIArmada\FilamentDocs\Resources\DocResource\Schemas\DocForm as BaseDocForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DocForm extends BaseDocForm
{
    public static function configure(Schema $schema): Schema
    {
        return parent::configure($schema)
            ->schema([
                // Extend with custom fields
                TextInput::make('custom_field'),
            ]);
    }
}
```
