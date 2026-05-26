---
title: Templates
---

## Templates

Templates are declarative JSON layouts stored on `DocTemplate::layout`. The renderer walks approved block definitions and converts the document data plus Tiptap JSON body into sanitized HTML for online viewing and optional PDF export.

Blade views are package-owned render targets only. Do not store user-authored Blade, PHP, or arbitrary component class names in templates.

## Breaking Changes

`view_name` is no longer the template mechanism. Migrate templates into `layout` JSON using supported block types, then render documents through `DocRenderService`.

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
use AIArmada\Docs\Models\DocTemplate;
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
use AIArmada\Docs\Services\DocRenderService;
use Illuminate\Support\HtmlString;

/** @var Doc $doc */
/** @var DocRenderService $renderer */
$renderer = app(DocRenderService::class);

$html = $renderer->renderHtml($doc, RenderAudience::CustomerView);

if ($html instanceof HtmlString) {
    echo $html->toHtml();
}
```

## PDF Export

PDF generation uses the same template layout and document data as online rendering.

```php
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;

/** @var Doc $doc */
$pdfContents = app(DocRenderService::class)->renderPdf($doc);
```

## Share Links

Public customer access uses hashed tokens and action permissions. Raw document IDs are not exposed.

```php
use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocRenderService;
use Carbon\CarbonImmutable;

/** @var Doc $doc */
$shareLink = app(DocRenderService::class)->createShareLink(
    $doc,
    new ShareLinkData(
        allowedActions: [ShareLinkAction::View, ShareLinkAction::Pdf],
        expiresAt: CarbonImmutable::now()->addDays(14),
    ),
);
```

## Rich Content

Document body content is stored as Tiptap JSON on `Doc::body`. Static rich text sections in templates are also JSON payloads. Render rich content through the package renderer so merge tags and sanitization are applied consistently.
