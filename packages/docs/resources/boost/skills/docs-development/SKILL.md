---
name: docs-development
description: Develop Docs integrations using Laravel Dune boundaries. Document creation, versioning, delivery, and rendering.
---

# Docs development

- Use `Data\DocData` for document creation and integer minor-unit values.
- Use `Contracts\DocumentPaymentRecorder`, `Contracts\DocumentVersions`, and the document service for changes. Do not mutate document versions or payment totals directly.
- Use `Contracts\DocumentApprovalRequests` to request or remove pending approvals; never delete settled approvals.
- Use `Contracts\DocumentApprovals` for approval decisions; resolve actor permissions at the authenticated entry point.
- Use `Contracts\DocumentEInvoiceSubmissions` to record and update MyInvois submission evidence; the document and submission identities are immutable and retries return the existing record.
- Use `Contracts\DocumentWorkflows` to create workflows, add steps, and remove workflows within the current owner boundary.
- Use `Contracts\DocumentSequenceSettings` for numbering configuration; issued or reserved sequences retain their numbering settings and cannot be deleted.
- Use `Contracts\DocumentTemplates` for document template mutations and default selection.
- Use `Contracts\DocumentEmailTemplates` for email template creation, editing, duplication, and removal.
- Keep rendering and delivery tenant-scoped; public share and tracking endpoints must validate their tokens and prevent sensitive caching.
- Use the canonical `EntryPoints` namespaces for jobs and HTTP controllers.

```php
use AIArmada\Docs\Data\DocData;

$data = DocData::from(['doc_type' => 'invoice', 'items' => [['quantity' => 1, 'unit_price_minor' => 1000]]]);
```

## Verification

From the commerce monorepo root:

```sh
composer --working-dir=packages/docs arch:check
./vendor/bin/pint --test packages/docs
./vendor/bin/pest tests/src/Docs
```

Do not add compatibility aliases, migration backfills, host application imports, or inline static-analysis suppressions.
