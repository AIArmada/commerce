---
title: Overview
---

# Tax Package

A comprehensive tax calculation package for Laravel commerce applications with support for multiple zones, rates, exemptions, and compound taxes.

## Features

- **Multi-zone Support** - Define tax zones by country, state, or postcode ranges
- **Flexible Tax Classes** - Categorize products with different tax treatments (standard, reduced, zero, exempt)
- **Compound Taxes** - Stack taxes on top of other taxes (e.g., provincial + federal)
- **Tax Exemptions** - Grant exemptions to specific customers with document verification
- **Owner Scoping** - Full multi-tenancy support via `commerce-support`
- **Spatie Settings** - Runtime-configurable tax settings without code changes
- **Activity Logging** - Track all tax configuration changes

## Core Concepts

### Tax Zones

Geographic regions where specific tax rules apply. Zones can be defined by:
- Country (e.g., Malaysia, Singapore)
- State/Province (e.g., Selangor, California)
- Postcode ranges (e.g., 40000-49999)

### Tax Rates

Percentage-based rates attached to zones and tax classes. Rates are stored as **basis points** (600 = 6.00%) for precision.

### Tax Classes

Categories for products that determine which rate applies:
- `standard` - Default rate for most products
- `reduced` - Lower rate for essential goods
- `zero` - 0% rate (still tracked for reporting)
- `exempt` - Not subject to tax

### Tax Exemptions

Customer-specific exemptions that bypass normal tax calculation. Supports:
- Certificate uploads
- Approval workflows
- Zone-specific or global exemptions
- Expiration dates

## Quick Example

```php
use AIArmada\Tax\Facades\Tax;

// Calculate tax on RM 100.00 (10000 cents)
$result = Tax::calculateTax(10000, 'standard', $zoneId);

echo $result->taxAmount;        // 600 (6% tax = RM 6.00)
echo $result->getFormattedRate(); // "6.00%"
echo $result->zoneName;         // "Malaysia"
```

## Package Structure

```
packages/tax/
├── config/tax.php           # Configuration
├── database/
│   ├── factories/           # Test factories
│   ├── migrations/          # Database schema
│   └── settings/            # Spatie settings migrations
├── src/
│   ├── Contracts/           # Interfaces
│   ├── Data/                # DTOs (TaxResultData)
│   ├── Exceptions/          # Custom exceptions
│   ├── Facades/             # Tax facade
│   ├── Models/              # Eloquent models
│   ├── Services/            # TaxCalculator
│   ├── Settings/            # Spatie settings classes
│   └── Support/             # Owner scoping helpers
└── docs/                    # This documentation
```

## Requirements

- PHP 8.4+
- Laravel 11+
- `spatie/laravel-settings` for runtime configuration
- `spatie/laravel-activitylog` for audit trails
- `aiarmada/commerce-support` for multi-tenancy (optional)
