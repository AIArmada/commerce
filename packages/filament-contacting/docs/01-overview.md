---
title: Filament Contacting Overview
---

# Filament Contacting

The `aiarmada/filament-contacting` package is a **Filament v5 adapter** for `aiarmada/contacting`.

## What it is

- Administrative UI for managing contact methods (email, phone, WhatsApp, etc.)
- Administrative UI for managing social profiles (Facebook, Instagram, TikTok, etc.)
- Read-only viewer for contact snapshots

## What it is not

- **Not** a domain package. Contact/social business logic, normalization, and persistence live in `aiarmada/contacting`.
- **Not** a communication tool. No email/SMS/WhatsApp sending.
- **Not** a sharing tool. No share buttons or share tracking.
- **Not** an addressing tool. No maps, addresses, or location data.

## Relation Managers (Recommended)

The primary way to use this package is via **relation managers**:

- `ContactMethodsRelationManager` — embedded in any resource whose model uses the `HasContactMethods` trait.
- `SocialProfilesRelationManager` — embedded in any resource whose model uses the `HasSocialProfiles` trait.

Relation managers are safer than standalone resources because they are automatically scoped to the parent record.

## Standalone Resources (Disabled by Default)

Standalone resources (`ContactMethodResource`, `SocialProfileResource`, `ContactSnapshotResource`) are **disabled by default** because:

- A central list can accidentally expose cross-owner records in tenant-scoped apps.
- Creating contacts without a parent entity (contactable/socialable) is usually an error.
- Snapshots are historical and should never be edited.

Enable them only when you have safe owner/contactable context.

## Snapshots are Read-Only

Contact snapshots preserve point-in-time copies of contact/social data. They cannot be created, edited, or deleted from the UI.