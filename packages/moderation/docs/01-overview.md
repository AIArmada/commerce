---
title: Overview
---

# Overview

`aiarmada/moderation` provides a lightweight moderation domain for Laravel applications. It records blocks and moderation actions against any Eloquent model using polymorphic relations.

## What this package owns

- Block records with a reason, status, expiry, lift metadata, and optional notes
- Moderation actions with a type, reason, and optional metadata
- Traits for models that can be blocked or recorded against
- Actions that create the records in transactions
- Configurable table names and JSON column type selection
- Owner-scoped moderation records and validation for tenant-owned models

## What this package does not own

- Admin UI or dashboards
- Policy decisions about when to block, warn, or lift a block
- The content being moderated
- Database foreign keys, cascades, or soft deletes

## Core concepts

- **Block** - an active, expired, or lifted moderation hold on a model
- **Moderation action** - a recorded moderation event such as warn, mute, suspend, or ban
- **Block reason** - the canonical reason code for a block
- **Moderation action type** - the canonical action code for a moderation event

## Key features

- Polymorphic moderation targets and actor references
- UUID primary keys for all stored records
- Enum-backed status and reason columns
- Immutable timestamp casts for expiry and lift times
- Transactional record creation via Actions
- Owner-safe validation for models that opt into `HasOwner`

## Models, traits, and actions

| Surface | Purpose |
| --- | --- |
| `Models\Block` | Stores block state, expiry, lift metadata, and notes |
| `Models\ModerationAction` | Stores moderation events and metadata |
| `Traits\HasBlocks` | Adds `blocks()`, `activeBlocks()`, and `block()` helpers |
| `Traits\HasModerationActions` | Adds `moderationActions()` and `recordModerationAction()` helpers |
| `Actions\BlockEntityAction` | Creates block records in a transaction |
| `Actions\RecordModerationAction` | Creates moderation action records in a transaction |

## Requirements

- PHP 8.4+
- Laravel 13+
- `aiarmada/commerce-support`
