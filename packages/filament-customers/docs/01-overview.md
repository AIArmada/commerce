---
title: Overview
---

# Filament Customers Plugin

The Filament Customers package provides the Filament admin UI for the `aiarmada/customers` domain package.

## Purpose

Use this package when you need panel resources and dashboard widgets for customer and segment management inside Filament.

## What this package owns

- The `FilamentCustomersPlugin` panel plugin
- `CustomerResource` and `SegmentResource`
- Customer relation managers for addresses and notes
- Dashboard widgets such as customer stats and recent customers
- Filament-side owner-safe query wiring for customer and segment administration

## What this package does not own

- The customer domain models, policies, events, and segmentation engine
- Customer authentication, registration, or account-management UI outside Filament
- Owner resolution itself beyond consuming shared `commerce-support` and `customers` behavior
- Orders, checkout, pricing, or other downstream customer consumers

## Related packages

- `aiarmada/customers` is the source of truth for the customer domain model and policies
- `aiarmada/commerce-support` provides shared owner-scoping utilities used by the resources
- Other `filament-*` packages may surface related data, but customer and segment administration lives here

## Main resources or surfaces

- `FilamentCustomersPlugin`
- `CustomerResource`
- `SegmentResource`
- `AddressesRelationManager`
- `NotesRelationManager`
- `CustomerStatsWidget`
- `RecentCustomersWidget`

## Features

### Customer Resource
- **Full CRUD**: Create, read, update, and delete customer records
- **Rich Forms**: Comprehensive forms with validation
- **Advanced Filters**: Filter by status, segments, marketing preferences
- **Bulk Actions**: Mass operations on multiple customers
- **Infolist Views**: Detailed customer information display
- **Global Search**: Quick customer lookup across the admin panel

### Segment Resource
- **Segment Management**: Create and manage customer segments
- **Automatic/Manual**: Support for both rule-based and manual segments
- **Condition Builder**: Visual interface for segment rules
- **Rebuild Actions**: One-click segment rebuilding
- **Member Preview**: See segment members before saving

### Relation Managers
- **Addresses**: Manage customer addresses inline
- **Notes**: Add internal and customer-visible notes

### Widgets
- **Customer Stats**: Real-time customer metrics and trends
- **Recent Customers**: Display of recently registered customers

### Owner Scoping
- **Automatic Filtering**: All queries respect owner boundaries
- **No UI Trust**: Server-side validation of all operations
- **Consistent Scoping**: Owner context applied across all resources

## Owner scoping and security notes

- Resource list queries use owner-aware scoping through `OwnerUiScope::apply(..., includeGlobal: false)`.
- Relationship selects for segments and customers are scoped server-side, not just filtered in the UI.
- Manual segment/customer assignment revalidates submitted IDs before syncing relationships.
- Bulk actions authorize each record before mutation instead of trusting table selection.

## Related integrations

Works seamlessly with:
- **Filament v5**: Built for the latest Filament version
- **Customers Package**: Depends on aiarmada/customers core package
- **Multi-Tenancy**: Full owner scoping support
- **Authentication**: Respects Laravel policies
- **Other Filament Plugins**: Integrates with other commerce Filament packages

## Architecture

The plugin follows Filament best practices:
- **Resources**: CustomerResource, SegmentResource
- **Pages**: List, Create, Edit, View pages for each resource
- **Relation Managers**: Nested relationships on customer record
- **Widgets**: Dashboard statistics and insights
- **Owner Scoping**: Centralized owner query logic
- **Policies**: Authorization via Laravel policies

## Requirements

- PHP 8.4+
- Filament 5.6+
- aiarmada/customers package

## Read next

- [Installation](02-installation.md) - Set up the plugin
- [Configuration](03-configuration.md) - Understand where this package is configured
- [Usage](04-usage.md) - Learn about resources and common admin flows
- [Widgets](05-widgets.md) - Dashboard widgets guide
- [Troubleshooting](99-troubleshooting.md) - Debug common issues
