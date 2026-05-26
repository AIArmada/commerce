---
title: "Proposal: Rich Text Body for Business Documents"
---

# Proposal: Rich Text Body for Business Documents

## Problem

Today, the `docs` package produces PDFs entirely from structured data (line items, notes, terms) rendered through Blade templates. This works well for invoices, receipts, and delivery notes — documents with predictable tabular layouts. But it falls short for:

- **Quotations / proposals** — where narrative, formatting, and visual persuasion matter as much as the line items
- **Proforma invoices** — where you want to present payment terms, a project scope, or a personalised message alongside the numbers
- **Custom document types** — where the "body" *is* the document (letters, memos, cover pages)

Users can currently put free-form text into `notes` or `terms` (plain `Textarea`), but these are fixed-position text blobs with no formatting — bold, headings, lists, tables, or inline images are impossible.

## Proposal

Add an optional **rich-text `body` field** to the `Doc` model, composed via Filament's Tiptap-based `RichEditor`, rendered in the PDF alongside the existing structured content.

### Design Principles

1. **Additive** — the rich body complements existing structured data; it does not replace it
2. **Config-driven** — each doc type decides whether rich body is available
3. **Positionable** — the body can render before items, after items, or as the sole content
4. **Template-decoupled** — existing Blade templates work unchanged; new templates can opt in via a simple conditional

---

## Data Model

### Migration: `add_rich_body_to_docs_table`

```php
Schema::table('docs', function (Blueprint $table) {
    $table->longText('body')->nullable()->after('terms');
    $table->string('body_position', 20)->default('before_items')->after('body');
});
```

| Column | Type | Default | Description |
|---|---|---|---|
| `body` | `longText` | `null` | Tiptap-generated HTML content |
| `body_position` | `string(20)` | `before_items` | `before_items`, `after_items`, or `full` |

### Doc Model Cast

```php
protected function casts(): array
{
    return [
        // ... existing casts
        'body' => 'string',
    ];
}
```

No special cast needed — `longText` returns a string natively.

---

## Configuration

### Per-Type Opt-In (`config/docs.php`)

Each doc type gets a `rich_body` flag:

```php
'types' => [
    'invoice' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'INV'],
        'rich_body' => false,
    ],
    'quotation' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'QUO'],
        'rich_body' => true,
    ],
    'receipt' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'RCP'],
        'rich_body' => false,
    ],
    'credit_note' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'CN'],
        'rich_body' => false,
    ],
    'delivery_note' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'DN'],
        'rich_body' => false,
    ],
    'proforma_invoice' => [
        'default_template' => 'doc-default',
        'numbering' => ['strategy' => DefaultNumberStrategy::class, 'prefix' => 'PI'],
        'rich_body' => true,
    ],
],
```

### Global Defaults Section

A new top-level config key defines sensible defaults:

```php
'rich_body' => [
    'enabled_by_default' => false,
    'default_position' => 'before_items',
    'allowed_positions' => ['before_items', 'after_items', 'full'],
    'toolbar' => 'default', // references a toolbar preset
],
```

---

## Filament Form UI (`DocForm`)

A collapsible "Rich Body" section between Customer Information and Line Items:

```php
Section::make('Rich Body')
    ->collapsible()
    ->collapsed(fn (Get $get): bool => blank($get('body')))
    ->hidden(fn (Get $get): bool => ! config(
        "docs.types.{$get('doc_type')}.rich_body",
        config('docs.rich_body.enabled_by_default', false),
    ))
    ->schema([
        RichEditor::make('body')
            ->label('Document Body')
            ->helperText('Compose a formatted narrative body. Supports headings, bold, italic, lists, and tables.')
            ->columnSpanFull()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3'],
                ['blockquote', 'bulletList', 'orderedList'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(config('docs.storage.disk', 'public'))
            ->fileAttachmentsDirectory(config('docs.storage.attachments_directory', 'docs/attachments')),

        Select::make('body_position')
            ->label('Body Position')
            ->options([
                'before_items' => 'Before Items',
                'after_items' => 'After Items',
                'full' => 'Full Page (no items)',
            ])
            ->default(config('docs.rich_body.default_position', 'before_items'))
            ->hidden(fn (Get $get): bool => blank($get('body')))
            ->reactive(),
    ]),
```

### Behavior Notes

| Doc Type | Section shown? | Reason |
|---|---|---|
| `invoice` | No | `rich_body: false` in config |
| `receipt` | No | `rich_body: false` in config |
| `quotation` | Yes | `rich_body: true` in config |
| `proforma_invoice` | Yes | `rich_body: true` in config |
| (new custom type) | Depends | Configurable per-type |

When body is empty, the `body_position` select hides automatically. When body has content and position is `full`, the Line Items and Amounts sections can be hidden (or disabled) since they become redundant.

---

## Doc Model Helper

```php
use Filament\Forms\Components\RichEditor\RichContentRenderer;

// On the Doc model
public function getBodyPosition(): string
{
    return $this->body_position ?? config('docs.rich_body.default_position', 'before_items');
}

public function getRenderedBody(): ?string
{
    if (blank($this->body)) {
        return null;
    }

    $body = RichContentRenderer::make($this->body)
        ->fileAttachmentsDisk(config('docs.storage.disk', 'public'))
        ->toHtml();

    // Replace template variables after rendering
    $variables = [
        '{{doc_number}}' => e($this->doc_number),
        '{{customer_name}}' => e($this->customer_data['name'] ?? ''),
        '{{total}}' => e(number_format($this->total, 2)),
        '{{currency}}' => e($this->currency),
        '{{due_date}}' => $this->due_date?->format('d M Y') ?? '',
        '{{issue_date}}' => $this->issue_date->format('d M Y'),
        '{{company_name}}' => e($this->company_data['name'] ?? config('docs.company.name', '')),
    ];

    return str_replace(array_keys($variables), array_values($variables), $body);
}
```

Uses Filament's `RichContentRenderer` to resolve image URLs (handles storage symlinks, private disk signed URLs, S3 paths) before variable substitution.

---

No changes to the PDF generation pipeline itself — `spatie/laravel-pdf` still receives the rendered Blade HTML. The only difference is that the HTML now includes the rich body div.

### RichContentRenderer for Image URL Resolution

Filament v5 provides `RichContentRenderer` to render stored rich content back to HTML. This is essential when the body contains uploaded images — the renderer resolves stored image paths back to accessible URLs, handling disk configuration, visibility (public/private), and temporary signed URLs:

```blade
@use('Filament\Forms\Components\RichEditor\RichContentRenderer')

{{-- Rich body: before items --}}
@if($doc->body && $doc->getBodyPosition() === 'before_items')
    <div class="rich-body prose max-w-none mt-6 mb-8">
        {{ RichContentRenderer::make($doc->body)
            ->fileAttachmentsDisk(config('docs.storage.disk', 'public'))
            ->toHtml() }}
    </div>
@endif
```

Using `RichContentRenderer` (instead of raw `{!! $doc->body !!}`) means:
- Uploaded image files get proper URLs resolved (storage symlink, absolute URL, or temporary signed URL for private disks)
- The disk and visibility config is applied consistently on both write and read
- If the storage backend changes (e.g., local → S3), only the disk config needs updating

### Security: HTML Sanitization

### Tailwind Prose for PDF Styling

The rich body HTML needs clean typography in the PDF. The `prose` class from `@tailwindcss/typography` should be included in the template's CSS:

```diff
- <script src="https://cdn.tailwindcss.com"></script>
+ <script src="https://cdn.tailwindcss.com"></script>
+ <script>
+   tailwind.config = {
+     plugins: [require('@tailwindcss/typography')],
+   }
+ </script>
```

Note that `@tailwindcss/typography` is a Tailwind plugin, not a CDN module. For CDN usage, add the plugin via the `plugins` config key. For compiled CSS, require `@tailwindcss/typography` in your `tailwind.config.js`.

### Security: HTML Sanitization

```php
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

$sanitizer = new HtmlSanitizer(
    (new HtmlSanitizerConfig())
        ->allowSafeElements()
        ->allowRelativeLinks()
        ->allowRelativeMedias()
        ->allowLinkSchemes(['https'])
        ->allowElement('img', ['src', 'alt', 'width', 'height'])
        ->allowElement('table', ['class'])
        ->allowElement('tr', ['class'])
        ->allowElement('td', ['class', 'colspan', 'rowspan'])
        ->allowElement('th', ['class', 'colspan', 'rowspan'])
);

return $sanitizer->sanitize($body);
```

This allows useful formatting (headings, lists, tables, bold, italic, links, images) while stripping dangerous tags (script, iframe, object, etc.).

---

## Variable Substitution in Rich Body

Same pattern as `DocEmailTemplate`: users can embed `{{placeholders}}` in the RichEditor content that get replaced at render time:

| Variable | Replaced With |
|---|---|
| `{{doc_number}}` | INV-202605-001 |
| `{{customer_name}}` | Acme Corp |
| `{{total}}` | 1,250.00 |
| `{{currency}}` | MYR |
| `{{due_date}}` | 26 Jun 2026 |
| `{{issue_date}}` | 26 May 2026 |
| `{{company_name}}` | Your Company |

No Blade evaluation — just `str_replace()` for safety.

---

## DocService Integration

### Create / Update

The `DocData` DTO gains optional `body` and `body_position` properties:

```php
class DocData extends Data
{
    public function __construct(
        // ... existing properties
        public readonly ?string $body = null,
        public readonly ?string $body_position = null,
    ) {}
}
```

`DocService::create()` and `DocService::update()` pass these through to the model. No additional validation needed — the form already constrains by doc type.

### Validation Rule

Add a validation rule on the service layer:

```php
'rules' => [
    'body' => [
        'nullable',
        'string',
        'max:65535',
        new RequiredIf(fn () => $this->data->body_position === 'full' && empty($this->data->items)),
    ],
    'body_position' => [
        'nullable',
        'string',
        Rule::in(config('docs.rich_body.allowed_positions', ['before_items', 'after_items', 'full'])),
    ],
],
```

If `body_position` is `full`, items become optional (since the body IS the document). This is a soft validation — the PDF simply skips the items table if position is `full`.

---

## Migration Path

### Backward Compatibility

- `body` is nullable — existing documents are unaffected
- `body_position` defaults to `before_items` — existing code that never sets it sees no change
- Blade templates that don't reference `$doc->getRenderedBody()` render identically to today
- **Existing templates work without modification** — the rich body blocks are `@if` conditionals

### Zero-Touch for Existing Users

| Surface | Impact |
|---|---|
| Existing docs | No change — `body` is null, not rendered |
| Existing PDFs | Same output as before |
| Existing Filament forms | Section is hidden for doc types with `rich_body: false` |
| Existing API consumers | New fields are nullable; old data unaffected |
| Existing templates | `@if` guards mean no change unless template is updated |

---

## Limitations & Open Questions

1. **Images in PDF** — Tiptap's `RichEditor` supports inline images via `fileAttachments`. These will render in the PDF HTML, but Browsershot/headless Chrome needs to be able to resolve the image URLs. Filament's `RichContentRenderer` handles this by generating accessible URLs based on the configured disk and visibility. For private disks, temporary signed URLs are used.

2. **JSON vs HTML storage** — `RichEditor` supports `->json()` to store content as TipTap JSON instead of HTML. HTML is simpler for direct Blade rendering; JSON preserves structured data for programmatic manipulation. The proposal defaults to HTML for simplicity. If JSON is preferred, the `getRenderedBody()` helper would need `RichContentRenderer::make()->toHtml()` to convert on read.

3. **Custom blocks as future extension** — Filament v5 `RichEditor` supports [custom TipTap blocks](https://filamentphp.com/docs/5.x/forms/rich-editor#using-custom-blocks). These could let users insert structured elements (e.g., "price table", "signature block", "terms accordion") directly into the rich body. Not needed for v1 but a natural future extension.

4. **No revision diff for body** — `DocVersion` stores a full `snapshot` JSON, so body changes are captured in version history. But there's no HTML-aware diff for rich text. A future enhancement could add visual diffing for the body field via the `DomDiff` package or similar.

5. **Complex layouts** — `before_items` / `after_items` / `full` cover the common cases. Truly custom layouts (e.g., body split across two columns with items in between) would still require a custom Blade template. The proposal stays opinionated to avoid scope creep.

6. **Print CSS for rich HTML** — The Tiptap output uses Tailwind prose classes for screen rendering. PDF rendering via Browsershot may need additional print-specific CSS (`@page`, page breaks, orphans/widows) to handle multi-page rich bodies gracefully. This is a template concern, not a data concern.

---

## Implementation Order

| Step | What | Blast Radius |
|---|---|---|---|
| 1 | Migration (add `body`, `body_position`) | DB only |
| 2 | Model cast + `getRenderedBody()` using `RichContentRenderer` | Doc model |
| 3 | `DocData` DTO update | Service layer |
| 4 | `DocForm` Rich Body section with `RichEditor` | Filament form |
| 5 | `DocService` create/update passthrough | Service layer |
| 6 | Config per-type `rich_body` flag | Config |
| 7 | Default Blade template `@if` blocks + Tailwind prose | Template |
| 8 | Sanitizer in `getRenderedBody()` | Security |
| 9 | Tests: create doc with body, PDF output contains body, body omitted for disabled types | QA |

Each step is independently revertible. Steps 1–3 alone unblock API-driven usage; steps 4–5 add the Filament UI.