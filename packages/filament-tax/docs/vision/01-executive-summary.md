# Filament Tax - Executive Summary

> **Document:** 01 of 04  
> **Package:** `aiarmada/filament-tax`  
> **Status:** Vision  
> **Last Updated:** December 2025

---

## Vision Statement

Deliver a **comprehensive tax configuration interface** that enables merchants to easily manage tax zones, rates, and classes through an intuitive admin panel with visual zone mapping and compliance-ready reporting.

---

## Core Features

### 1. Tax Zone Management
Geographic-based tax configuration.

```
┌────────────────────────────────────────────────────────────────┐
│ TAX ZONES                                          [+ Create]   │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ NAME           │ TYPE      │ COUNTRIES/REGIONS    │ RATES      │
│ ───────────────┼───────────┼──────────────────────┼────────────│
│ Malaysia       │ Country   │ MY                   │ 1 rate     │
│ EU VAT Zone    │ Region    │ DE, FR, IT, ES +20   │ 23 rates   │
│ US Sales Tax   │ State     │ CA, NY, TX +47       │ 50 rates   │
│ Singapore      │ Country   │ SG                   │ 1 rate     │
│ Australia      │ Country   │ AU                   │ 2 rates    │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 2. Tax Rate Configuration
```
┌────────────────────────────────────────────────────────────────┐
│ ZONE: Malaysia                                                  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ TAX CLASS       │ RATE NAME │ PERCENTAGE │ SHIPPING │ STATUS   │
│ ────────────────┼───────────┼────────────┼──────────┼──────────│
│ Standard        │ SST       │    6%      │    ✓     │ Active   │
│ Reduced         │ SST       │    0%      │    ✓     │ Active   │
│ Zero-Rated      │ SST       │    0%      │    ✗     │ Active   │
│ Exempt          │ —         │    0%      │    ✗     │ Active   │
│                                                                 │
│ [+ Add Rate]                                                    │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 3. Tax Class Management
Product tax categorization.

```
┌────────────────────────────────────────────────────────────────┐
│ TAX CLASSES                                        [+ Create]   │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ NAME           │ DESCRIPTION                      │ PRODUCTS   │
│ ───────────────┼──────────────────────────────────┼────────────│
│ Standard       │ Default tax class                │ 1,234      │
│ Reduced        │ Essential goods, lower rate      │ 567        │
│ Zero-Rated     │ Exported goods, 0% but claimable │ 89         │
│ Exempt         │ No tax applicable                │ 45         │
│ Digital        │ Digital goods (VAT MOSS rules)   │ 123        │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### 4. Tax Exemption Certificates
Customer exemption management.

```
┌────────────────────────────────────────────────────────────────┐
│ EXEMPTION CERTIFICATES                             [+ Add New]  │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│ CUSTOMER        │ CERTIFICATE NO │ ZONES    │ EXPIRES   │ STATUS│
│ ────────────────┼────────────────┼──────────┼───────────┼───────│
│ ABC Corp        │ SST-EX-12345   │ Malaysia │ Dec 2026  │ ● Valid│
│ XYZ Trade       │ VAT-EU-67890   │ EU       │ Mar 2025  │ ⚠ Soon │
│ Charity Inc     │ NPO-US-11111   │ US       │ —         │ ● Valid│
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

---

## Dashboard Widgets

### Tax Collection Summary
```
┌────────────────────────────────────────────────────────────────┐
│ TAX COLLECTION (This Month)                                     │
├──────────────┬──────────────┬──────────────┬──────────────────┤
│   Collected  │    Orders    │   Avg Rate   │   Exemptions     │
│  RM 12,456   │     567      │    5.8%      │      23          │
└──────────────┴──────────────┴──────────────┴──────────────────┘
```

### Tax by Zone
Breakdown of tax collected by zone.

### Expiring Certificates
Alerts for soon-to-expire exemptions.

---

## Implementation Phases

| Phase | Scope | Status |
|-------|-------|--------|
| 1 | TaxZoneResource | 🔴 Not Started |
| 2 | TaxRateResource | 🔴 Not Started |
| 3 | TaxClassResource | 🔴 Not Started |
| 4 | TaxExemptionResource | 🔴 Not Started |
| 5 | Dashboard & Widgets | 🔴 Not Started |

---

## Navigation

**Next:** [02-tax-zone-resource.md](02-tax-zone-resource.md)
