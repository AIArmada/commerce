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

**Cause**: Old data was written before the model-level save hooks existed, or a raw query/database update bypassed them.

**Fix**: Save the model normally so the primary uniqueness hooks can rebalance siblings. Use `SetPrimaryContactMethodAction` or pass `is_primary: true` to `CreateContactMethodAction` for explicit intent. Raw query updates can still bypass the hooks.

## Phone Normalization Surprises

Phone normalization uses `propaganistas/laravel-phone` when available: parsed
numbers are stored as E.164 in `normalized_value` with an international-format
`display_value`. When parsing fails, a lightweight fallback strips separators,
preserves a leading `+`, and prefixes the calling code for the given
`country_code` (AU, CA, GB, ID, MY, NZ, PH, SG, TH, US, VN); other country
codes pass the digits through unchanged.

If a number normalizes unexpectedly, check the `country_code` on the contact
method first — it selects both the parser region and the fallback calling
code.

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
- Default is `contacting.defaults.public_by_default` (`true`), except `email`, `phone`, `mobile`, `whatsapp`, and `fax` contact methods, which default to private
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
