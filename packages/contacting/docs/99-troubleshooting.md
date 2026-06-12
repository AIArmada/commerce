---
title: Contacting Troubleshooting
---

# Troubleshooting

## Contact Not Appearing Due to Owner Scope

If a contact method or social profile is not showing up in queries, check if owner scoping is enabled:

```php
config('contacting.features.owner.enabled') // true or false
```

When enabled, only records belonging to the current owner context are returned. Use explicit context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

OwnerContext::withOwner($owner, function () use ($institution) {
    return $institution->contactMethods()->get();
});
```

## Primary Contact Not Unique

**Problem**: Multiple contacts marked as primary for the same type and purpose.

**Cause**: The `is_primary` flag was set directly on the model instead of using the Actions.

**Fix**: Always use `SetPrimaryContactMethodAction` or pass `is_primary: true` to `CreateContactMethodAction`. Direct model updates bypass the deduplication logic.

## Phone Normalization Surprises

Phone normalization in v1 is conservative:

- Only supports basic MY (Malaysia) number conversion (`0xx` -> `+60xx`)
- Spaces, dashes, and parentheses are stripped
- International numbers starting with `+` are preserved as-is
- No E.164 compliance is guaranteed without a dedicated phone library

If your app needs robust international phone validation, add a phone library and extend `NormalizeContactMethodAction`.

## Social URL Not Parsed

Handle extraction from URLs only works for known platform patterns:

- Facebook, Instagram, TikTok, YouTube, LinkedIn, X/Twitter, Threads

If the URL uses a regional domain (e.g., `facebook.co.id`), it may not match. In that case, provide the handle explicitly:

```php
'handle' => 'username',
'url' => 'https://facebook.co.id/username',
```

## Public/Private Visibility Confusion

- `is_public` controls whether the contact is shown on public-facing pages
- Default is `true` (public)
- Set `is_public: false` for internal/admin-only contacts
- The `publicContactMethods()` trait method respects this flag
- Snapshots preserve the public/private state at capture time

## Snapshot Not Changing After Source Update

This is by design. Snapshots are point-in-time historical copies. The `payload` column stores a full copy of the source data at snapshot time.

If the source contact changes, the existing snapshot remains unchanged. Create a new snapshot if you need an updated copy.

## Config JSON Type Mismatch

If you see column type errors in migrations, check the `json_column_type` config:

```php
config('contacting.database.json_column_type') // 'json' or 'jsonb'
```

SQLite supports `json` but not `jsonb`. Set it in your `.env` or test config:

```env
CONTACTING_JSON_COLUMN_TYPE=json
```