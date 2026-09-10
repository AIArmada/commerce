---
title: Troubleshooting
---

# Troubleshooting

## Resources Not Showing

### Navigation Empty

Verify the plugin is registered and clear cached config:

```bash
php artisan config:clear
```

### Wrong Navigation Group

Check either:

- `config('filament-docs.navigation.group')`
- `FilamentDocsPlugin::make()->navigationGroup('...')`

## PDF Issues

### Generate PDF Action Fails

Check the underlying docs package first:

- PDF runtime/browser support is available
- the selected template view exists
- the configured storage disk is writable

### Download Returns 403 / 404

The download route is protected by auth middleware and owner-aware checks. Verify:

- the user is authenticated
- the document has a `pdf_path`
- the stored file still exists
- the current owner context matches the document

Preview routes use the same owner-safe binding. A Filament resource query or hidden navigation item is not a security boundary.

## Pending Approvals Empty

If the page is unexpectedly empty, check that the approval is assigned to the current user:

```php
DocApproval::where('assigned_to', auth()->id())
    ->where('status', \AIArmada\Docs\Enums\DocApprovalStatus::Pending)
    ->get();
```

## Widget Values Look Wrong

All widgets use owner-aware queries. If everything is zero, inspect the current owner context and the `docs.owner.*` configuration.

## Payment Action Rejects a Valid-Looking Amount

`RecordPaymentAction` delegates to the core payment recorder. Confirm the amount is a positive integer minor-unit value, uses the document currency, and does not exceed the outstanding balance.

## Need More Context

Review the base docs package troubleshooting guide in `packages/docs/docs/99-troubleshooting.md` for PDF, storage, and email issues coming from the underlying package.
