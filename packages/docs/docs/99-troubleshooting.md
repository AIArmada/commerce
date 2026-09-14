---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions for the Docs package.

## PDF Generation

### PDF Not Generating

**Symptom:** `generatePdf()` throws an error or returns empty content.

**Solutions:**

1. **Install Puppeteer**
   ```bash
   npm install puppeteer
   ```

2. **Check Node.js version**
   ```bash
   node --version  # Requires 18+
   ```

3. **Verify Chromium installation**
   Puppeteer should auto-download Chromium. If blocked by firewall:
   ```bash
   export PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
   # Then install Chromium manually
   ```

4. **Check the package render view and template layout**
   ```php
   view()->exists('docs::documents.show'); // Should be true
   $doc->template?->layout; // Should be an approved block layout array when a template is selected
   ```

### PDF Has Wrong Content

**Symptom:** PDF shows incorrect data or missing sections.

**Solutions:**

1. **Verify template is loaded**
   ```php
   $doc->template; // Should return DocTemplate model
   ```

2. **Render the canonical HTML output directly**
   ```php
   use AIArmada\Docs\Enums\RenderAudience;
   use AIArmada\Docs\Services\DocRenderService;

   app(DocRenderService::class)->renderHtml($doc, RenderAudience::CustomerView);
   ```

3. **Check template/payload compatibility**
   ```php
   // Throws when body is present without a rich_body block,
   // or when the selected template requires line items but items are empty.
   app(DocRenderService::class)->validateDocPayload($doc->template, $doc->body, $doc->items ?? []);
   ```

### Background Colors Not Printing

**Symptom:** PDFs show white background instead of styled colors.

**Solution:** Enable background printing in config or template:

```php
// config/docs.php
'pdf' => [
    'print_background' => true,
],

// Or per-template
DocTemplate::create([
    'settings' => [
        'pdf' => [
            'print_background' => true,
        ],
    ],
]);
```

---

## Document Creation

### "Invalid template selection" Error

**Symptom:** `ValidationException` when creating document with `doc_template_id`.

**Solutions:**

1. **Check template exists**
   ```php
   DocTemplate::find($templateId); // Should not be null
   ```

2. **Check owner scoping**
   If owner mode is enabled, template must belong to same owner:
   ```php
   DocTemplate::forOwner($owner)->find($templateId);
   ```

### Document Number Collision

**Symptom:** `InvalidArgumentException: Document number [...] is already in use.`

**Solutions:**

1. **Check which owner scope collided**
   Numbers are unique per `(owner_type, owner_id, doc_number)`, so different owners may legitimately share a format. The error means the number is taken inside the current owner scope.

2. **Let the sequence generate the number**
   Omit `doc_number` so `SequenceManager` assigns the next atomic value instead of reusing an explicit one.

3. **Check sequence configuration**
   ```php
   $sequence = DocSequence::where('doc_type', 'invoice')
       ->where('is_active', true)
       ->first();
   ```

### Totals Mismatch on Create

**Symptom:** `InvalidArgumentException: Document field 'total_minor' (...) does not match the items-derived value (...)`.

**Solutions:**

1. **Omit explicit totals when items are present**
   Totals are derived from items; only pass overrides when they exactly match the computed values.
2. **Pass totals without items**
   Item-less documents persist caller totals after non-negativity validation.

---

## Multi-Tenancy

### Documents Showing Across Tenants

**Symptom:** Users see documents from other tenants.

**Solutions:**

1. **Verify owner mode is enabled**
   ```php
   config('docs.owner.enabled'); // Should be true
   ```

2. **Check OwnerResolver is bound**
   ```php
   use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
   
   app()->bound(OwnerResolverInterface::class); // Should be true
   ```

3. **Verify current owner**
   ```php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   OwnerContext::resolve(); // Should return Model|null
   ```

### Global Templates Not Available

**Symptom:** Templates with `owner_type = null` not showing.

**Solution:** Enable `include_global`:

```php
// config/docs.php
'owner' => [
    'enabled' => true,
    'include_global' => true, // Add this
],
```

---

## Status Management

### Status Not Updating

**Symptom:** `markAsPaid()` or similar methods have no effect.

**Solutions:**

1. **Check current status**
   Some transitions are blocked:
   ```php
   $doc->cancel(); // Does nothing if already PAID
   ```

2. **Use service method for full tracking**
   ```php
   $docService->updateStatus($doc, \AIArmada\Docs\States\Paid::class, 'Manual override');
   ```

### Status History Not Recording

**Symptom:** `statusHistories` relation is empty after status change.

**Solutions:**

1. **Use model methods** which auto-create history:
   ```php
   $doc->markAsPaid();  // Creates history entry
   ```

2. **Check owner columns** when owner mode is enabled, history needs owner data

---

## Email

### Emails Not Sending

**Symptom:** `DocEmailService::send()` doesn't deliver emails.

**Checks:**

```php
config('docs.email.queue_enabled');
config('docs.email.attach_pdf');
config('docs.email.queue');
```

The package queues mail when `docs.email.queue_enabled` is true and sends immediately otherwise. Queued sends dispatch `SendDocEmailJob`, which transitions the email from `queued` to `sent` (or `failed`) — check the queue worker if rows stay `queued`.

### Tracking Tokens Invalid

**Symptom:** `trackOpen()` returns false.

**Solutions:**

1. **Check token age**
   Tokens expire after `docs.email.tracking.ttl_days` days (default 180).

2. **Check encryption key consistency**
   Tokens use Laravel's `Crypt` facade. Key changes invalidate tokens.

3. **Check token format**
   Tokens should be URL-safe encrypted JSON.

### Shared Link Returns 404

**Symptom:** `/docs/share/{token}` or `/docs/share/{token}/pdf` returns not found.

**Solutions:**

1. **Check expiry / revocation**
   ```php
   $shareLink->isExpired();
   $shareLink->isRevoked();
   ```

2. **Check allowed actions**
   ```php
   $shareLink->allows(\AIArmada\Docs\Enums\ShareLinkAction::View);
   $shareLink->allows(\AIArmada\Docs\Enums\ShareLinkAction::Pdf);
   ```

3. **Check owner context on the linked document**
   Share links re-enter the linked document's owner / explicit-global context before loading the document. If the stored owner tuple is malformed, link resolution fails closed with a 404.

---

## Configuration

### Config Changes Not Taking Effect

**Symptom:** Modified `config/docs.php` values not applied.

**Solutions:**

```bash
php artisan config:clear
php artisan cache:clear
```

### Table Names Wrong

**Symptom:** Queries fail with "table not found".

**Solution:** Verify migration prefix matches config:

```php
// config/docs.php
'database' => [
    'table_prefix' => 'docs_', // Must match migration
],
```

---

## Common Errors

### Class Not Found: DocData

**Error:** `Class AIArmada\Docs\Data\DocData not found`

**Solution:** Use correct namespace:
```php
use AIArmada\Docs\DataObjects\DocData; // Correct
// NOT: use AIArmada\Docs\Data\DocData;
```

### Migration Failed

**Error:** Migration fails on JSON columns.

**Solution:** Configure JSON column type for your database:

```php
// config/docs.php
'database' => [
    // Configure the column type via commerce_json_column_type() helper or env var
],
```

### Heroicon Not Found

**Error:** Heroicon class constant issues.

**Solution:** Ensure using Filament v5 compatible icon syntax. The package uses `Heroicon::OutlinedDocumentText` format.

---

## Getting Help

1. Check the [GitHub Issues](https://github.com/aiarmada/commerce/issues)
3. Ensure you're using compatible versions (PHP 8.4+, Laravel 13+)
