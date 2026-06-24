---
title: Troubleshooting
---

# Troubleshooting

## Blocks are not being created

1. Confirm the target model uses `HasBlocks`
2. Confirm the model is saved before calling `block()`
3. Check the moderation tables exist and the service provider is registered

## Owner validation fails

If the target model is tenant-owned, `block()` and `recordModerationAction()` expect a valid owner context.

1. Wrap the call in `OwnerContext::withOwner(...)`
2. Confirm the model exists for the current owner
3. Pass a global model only when that is intended

## Metadata columns look wrong

Set `MODERATION_JSON_COLUMN_TYPE` or `COMMERCE_JSON_COLUMN_TYPE` to match your database driver.

## Migration tables are missing

Run the package migrations:

```bash
php artisan migrate
```

If you published a custom table prefix, confirm the config and environment variables point to the same table names.
