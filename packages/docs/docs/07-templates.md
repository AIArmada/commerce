---
title: Templates
---

## Templates

Templates are declarative JSON layouts stored on `DocTemplate::layout`. The renderer walks approved block definitions and converts the document data plus Tiptap JSON body into sanitized HTML for online viewing and optional PDF export.

Blade views are package-owned render targets only. Do not store user-authored Blade, PHP, or arbitrary component class names in templates.

## Breaking Changes

`view_name` is no longer the template mechanism. Migrate templates into `layout` JSON using supported block types, then render documents through `DocumentRendering`.

## Supported Blocks

- `document_header`
- `parties`
- `document_metadata`
- `rich_body`
- `static_rich_text`
- `line_items`
- `totals`
- `notes_terms`
- `signature_payment`
- `page_break`
- `footer`

Layouts are validated by `TemplateBlockRegistry` before save and again before render.

## Creating Templates

```php
use AIArmada\Docs\Features\Templates\Models\DocTemplate;
use AIArmada\Docs\Support\TemplateBlockRegistry;

$template = DocTemplate::create([
    'name' => 'Modern Invoice',
    'slug' => 'modern-invoice',
    'description' => 'Online-first invoice layout',
    'doc_type' => 'invoice',
    'is_default' => true,
    'layout' => TemplateBlockRegistry::defaultLayout(),
    'settings' => [
        'pdf' => [
            'format' => 'a4',
            'orientation' => 'portrait',
            'print_background' => true,
            'margin' => [
                'top' => 10,
                'right' => 10,
                'bottom' => 10,
                'left' => 10,
            ],
        ],
    ],
]);
```

## Rendering Documents

```php
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\DocumentRendering;
use Illuminate\Support\HtmlString;

/** @var Doc $doc */
/** @var DocumentRendering $renderer */
$renderer = app(DocumentRendering::class);

$html = $renderer->renderHtml($doc, RenderAudience::CustomerView);

if ($html instanceof HtmlString) {
    echo $html->toHtml();
}
```

## PDF Export

PDF generation uses the same template layout and document data as online rendering.

PDF options resolve in this order:

1. `config('docs.pdf.*')`
2. `DocTemplate::settings['pdf']`
3. `Doc::metadata['pdf']`

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\DocumentRendering;

/** @var Doc $doc */
$pdfContents = app(DocumentRendering::class)->renderPdf($doc);
```

## Share Links

Public customer access uses hashed tokens and action permissions. Raw document IDs are not exposed.

```php
use AIArmada\Docs\Data\ShareLinkData;
use AIArmada\Docs\Data\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Contracts\DocumentSharing;
use Carbon\CarbonImmutable;

/** @var Doc $doc */
$shareLink = app(DocumentSharing::class)->issue(
    (string) $doc->getKey(),
    new ShareLinkData(
        allowedActions: [ShareLinkAction::View, ShareLinkAction::Pdf],
        expiresAt: CarbonImmutable::now()->addDays(14),
    ),
);

$plainToken = $shareLink->plainToken();
```

## Rich Content

Document body content is stored as Tiptap JSON on `Doc::body`. Static rich text sections in templates are also JSON payloads. Render rich content through the package renderer so merge tags and sanitization are applied consistently.

## Payload Validation

`DocumentRendering::validateDocPayload()` is called during create/update/convert flows when a template is selected.

- If you submit `body`, the template must contain a `rich_body` block.
- If the template contains a `line_items` block, you must submit at least one line item.

This keeps template capabilities and document payloads aligned before the package stores or renders the document.
