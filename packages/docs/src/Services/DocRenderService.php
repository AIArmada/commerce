<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\PdfRenderer;
use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\Data\PdfRenderOptions;
use AIArmada\Docs\Enums\DocMergeTag;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Runtime\TemplateRelationModel;
use AIArmada\Docs\Support\DocPdfPath;
use AIArmada\Docs\Support\DocTypeKey;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** @phpstan-type RelatedDocTemplate \AIArmada\Docs\Features\Templates\Models\DocTemplate */
final class DocRenderService
{
    public function __construct(
        private readonly RichContentRendererInterface $richContentRenderer,
        private readonly PdfRenderer $pdfRenderer,
    ) {}

    public function renderHtml(Doc $doc, RenderAudience $audience): HtmlString
    {
        return $this->renderWithTemplate($doc, $audience, $this->resolveTemplate($doc));
    }

    /** @param RelatedDocTemplate|null $template */
    private function renderWithTemplate(Doc $doc, RenderAudience $audience, ?Model $template): HtmlString
    {
        $doc->loadMissing('docable');

        $layout = $template?->layout ?: TemplateBlockRegistry::defaultLayout();

        TemplateBlockRegistry::assertValid($layout);

        $content = collect($layout)
            ->map(fn (array $block): string => $this->renderBlock($doc, $block, $audience))
            ->filter()
            ->implode("\n");

        return new HtmlString(view('docs::documents.show', [
            'doc' => $doc,
            'template' => $template,
            'audience' => $audience,
            'content' => new HtmlString($content),
        ])->render());
    }

    public function renderPdf(Doc $doc): string
    {
        [$html, $options] = $this->pdfInput($doc);

        return $this->pdfRenderer->render($html, $options);
    }

    public function storePdf(Doc $doc): string
    {
        $id = (string) $doc->getKey();

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $id);
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $snapshot = Doc::query()->forOwner()->findOrFail($id);
            [$html, $options, $fingerprint] = $this->pdfInput($snapshot);
            $path = DocPdfPath::forDocument($snapshot->doc_type, $id);
            $disk = $this->resolveStorageDisk($snapshot->doc_type);
            $storage = Storage::disk($disk);

            if ($snapshot->pdf_path === $path && $snapshot->pdf_fingerprint === $fingerprint && $storage->exists($path)) {
                return $path;
            }

            // Keep the expensive PDF driver outside the database transaction.
            $bytes = $this->pdfRenderer->render($html, $options);

            $published = $snapshot->getConnection()->transaction(function () use ($id, $fingerprint, $path, $disk, $bytes): bool {
                $locked = Doc::query()->forOwner()->lockForUpdate()->findOrFail($id);
                [, , $currentFingerprint] = $this->pdfInput($locked);

                if ($currentFingerprint !== $fingerprint
                    || DocPdfPath::forDocument($locked->doc_type, $id) !== $path
                    || $this->resolveStorageDisk($locked->doc_type) !== $disk) {
                    return false;
                }

                $storage = Storage::disk($disk);

                if ($locked->pdf_path === $path && $locked->pdf_fingerprint === $fingerprint && $storage->exists($path)) {
                    return true;
                }

                if (! $storage->put($path, $bytes)) {
                    throw new RuntimeException('Document PDF could not be stored.');
                }

                $locked->forceFill(['pdf_path' => $path, 'pdf_fingerprint' => $fingerprint])->save();

                return true;
            });

            if ($published) {
                return $path;
            }
        }

        throw new RuntimeException('Document changed during PDF generation. Retry the request.');
    }

    /** @return array{string, PdfRenderOptions, string} */
    private function pdfInput(Doc $doc): array
    {
        $template = $this->resolveTemplate($doc);
        $html = $this->renderWithTemplate($doc, RenderAudience::Pdf, $template)->toHtml();
        $options = $this->resolvePdfOptions($doc, $template);
        $fingerprint = hash('sha256', json_encode([$html, $options, $this->pdfRenderer::class], JSON_THROW_ON_ERROR));

        return [$html, $options, $fingerprint];
    }

    /** @param RelatedDocTemplate $template */
    public function validateDocPayload(Model $template, ?array $body, array $items): void
    {
        $layout = $template->layout ?? [];
        $usesBody = TemplateBlockRegistry::hasBlock($layout, DocTemplateBlockType::RichBody);
        $usesItems = TemplateBlockRegistry::hasBlock($layout, DocTemplateBlockType::LineItems);

        if (! $usesBody && filled($body)) {
            throw ValidationException::withMessages([
                'body' => __('The selected template does not contain a rich body block.'),
            ]);
        }

        if ($usesItems && $items === []) {
            throw ValidationException::withMessages([
                'items' => __('The selected template requires at least one line item.'),
            ]);
        }
    }

    /** @param RelatedDocTemplate|null $template */
    private function resolvePdfOptions(Doc $doc, ?Model $template): PdfRenderOptions
    {
        $defaults = [
            'format' => (string) config('docs.pdf.format', 'a4'),
            'orientation' => (string) config('docs.pdf.orientation', 'portrait'),
            'margin' => [
                'top' => (int) config('docs.pdf.margin.top', 10),
                'right' => (int) config('docs.pdf.margin.right', 10),
                'bottom' => (int) config('docs.pdf.margin.bottom', 10),
                'left' => (int) config('docs.pdf.margin.left', 10),
            ],
            'full_bleed' => (bool) config('docs.pdf.full_bleed', false),
            'print_background' => (bool) config('docs.pdf.print_background', true),
        ];

        $templatePdf = (array) ($template?->settings['pdf'] ?? []);
        $perDoc = (array) ($doc->metadata['pdf'] ?? []);

        $options = array_replace_recursive($defaults, $templatePdf, $perDoc);

        $format = mb_strtolower((string) ($options['format'] ?? $defaults['format']));
        $orientation = mb_strtolower((string) ($options['orientation'] ?? $defaults['orientation']));

        return new PdfRenderOptions(
            format: in_array($format, ['a3', 'a4', 'a5', 'letter', 'legal'], true) ? $format : $defaults['format'],
            orientation: in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : $defaults['orientation'],
            marginTop: $this->clampMargin(data_get($options, 'margin.top', $defaults['margin']['top'])),
            marginRight: $this->clampMargin(data_get($options, 'margin.right', $defaults['margin']['right'])),
            marginBottom: $this->clampMargin(data_get($options, 'margin.bottom', $defaults['margin']['bottom'])),
            marginLeft: $this->clampMargin(data_get($options, 'margin.left', $defaults['margin']['left'])),
            fullBleed: (bool) ($options['full_bleed'] ?? $defaults['full_bleed']),
            printBackground: (bool) ($options['print_background'] ?? $defaults['print_background']),
        );
    }

    private function clampMargin(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 10;
        }

        return max(0, min(100, (int) $value));
    }

    private function renderBlock(Doc $doc, array $block, RenderAudience $audience): string
    {
        $type = DocTemplateBlockType::from((string) $block['type']);
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        if (($data['visible'] ?? true) === false) {
            return '';
        }

        return match ($type) {
            DocTemplateBlockType::DocumentHeader => $this->renderHeader($doc, $data),
            DocTemplateBlockType::Parties => $this->renderParties($doc, $data),
            DocTemplateBlockType::DocumentMetadata => $this->renderMetadata($doc, $data),
            DocTemplateBlockType::RichBody => $this->renderRichBody($doc),
            DocTemplateBlockType::StaticRichText => $this->renderStaticRichText($doc, $data),
            DocTemplateBlockType::LineItems => $this->renderItems($doc, $data),
            DocTemplateBlockType::Totals => $this->renderTotals($doc, $data),
            DocTemplateBlockType::NotesTerms => $this->renderNotesTerms($doc, $data),
            DocTemplateBlockType::SignaturePayment => $this->renderSignaturePayment($data),
            DocTemplateBlockType::PageBreak => $audience === RenderAudience::Pdf ? '<div class="doc-page-break"></div>' : '<hr class="doc-section-break">',
            DocTemplateBlockType::Footer => $this->renderFooter($data),
        };
    }

    private function renderHeader(Doc $doc, array $data): string
    {
        $label = e((string) ($data['label'] ?? Str::headline((string) $doc->doc_type)));
        $docNumber = e((string) $doc->doc_number);
        $total = e(MoneyFormatter::formatMinor($doc->total_minor, $doc->currency));

        return <<<HTML
        <section class="doc-block doc-header">
            <div>
                <h1>{$label}</h1>
                <p>{$docNumber}</p>
            </div>
            <strong>{$total}</strong>
        </section>
        HTML;
    }

    private function renderParties(Doc $doc, array $data): string
    {
        $companyTitle = e((string) ($data['company_label'] ?? 'From'));
        $customerTitle = e((string) ($data['customer_label'] ?? 'Bill To'));

        return '<section class="doc-block doc-parties">' .
            $this->renderParty($companyTitle, (array) $doc->company_data) .
            $this->renderParty($customerTitle, (array) $doc->customer_data) .
            '</section>';
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function renderParty(string $title, array $party): string
    {
        $lines = array_filter([
            $party['name'] ?? null,
            $party['email'] ?? null,
            $party['phone'] ?? null,
            $party['address'] ?? null,
            collect([$party['city'] ?? null, $party['state'] ?? null, $party['postcode'] ?? null])->filter()->implode(', '),
            $party['country'] ?? null,
        ]);

        $body = collect($lines)
            ->map(static fn (mixed $line): string => '<p>' . e((string) $line) . '</p>')
            ->implode('');

        return "<div><h2>{$title}</h2>{$body}</div>";
    }

    private function renderMetadata(Doc $doc, array $data): string
    {
        $title = e((string) ($data['label'] ?? 'Document Details'));
        $dueDate = $doc->due_date?->format('M d, Y') ?? '-';
        $issueDate = $doc->issue_date->format('M d, Y');
        $status = e($doc->status->label());

        return <<<HTML
        <section class="doc-block">
            <h2>{$title}</h2>
            <dl class="doc-metadata">
                <div><dt>Issue Date</dt><dd>{$issueDate}</dd></div>
                <div><dt>Due Date</dt><dd>{$dueDate}</dd></div>
                <div><dt>Status</dt><dd>{$status}</dd></div>
            </dl>
        </section>
        HTML;
    }

    private function renderRichBody(Doc $doc): string
    {
        $html = $this->richContentRenderer->render($doc->body, DocMergeTag::valuesFor($doc))->toHtml();

        if ($html === '') {
            return '';
        }

        return '<section class="doc-block doc-prose">' . $html . '</section>';
    }

    private function renderStaticRichText(Doc $doc, array $data): string
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : null;
        $html = $this->richContentRenderer->render($content, DocMergeTag::valuesFor($doc))->toHtml();

        if ($html === '') {
            return '';
        }

        return '<section class="doc-block doc-prose">' . $html . '</section>';
    }

    private function renderItems(Doc $doc, array $data): string
    {
        $items = $doc->items ?? [];

        if ($items === []) {
            return '';
        }

        $title = e((string) ($data['label'] ?? 'Items'));
        $rows = collect($items)
            ->map(function (array $item) use ($doc): string {
                $quantity = (int) ($item['quantity'] ?? 1);
                $unitPriceMinor = (int) ($item['unit_price_minor'] ?? 0);
                $lineTotalMinor = $quantity * $unitPriceMinor;
                $name = e((string) ($item['name'] ?? $item['description'] ?? 'Item'));
                $unitPrice = e(MoneyFormatter::formatMinor($unitPriceMinor, (string) $doc->currency));
                $lineTotal = e(MoneyFormatter::formatMinor($lineTotalMinor, (string) $doc->currency));
                $description = filled($item['description'] ?? null) && isset($item['name'])
                    ? '<small>' . e((string) $item['description']) . '</small>'
                    : '';

                return <<<HTML
                <tr>
                    <td><strong>{$name}</strong>{$description}</td>
                    <td class="doc-number">{$quantity}</td>
                    <td class="doc-number">{$unitPrice}</td>
                    <td class="doc-number">{$lineTotal}</td>
                </tr>
                HTML;
            })
            ->implode('');

        return <<<HTML
        <section class="doc-block">
            <h2>{$title}</h2>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="doc-number">Qty</th>
                        <th class="doc-number">Unit Price</th>
                        <th class="doc-number">Amount</th>
                    </tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>
        </section>
        HTML;
    }

    private function renderTotals(Doc $doc, array $data): string
    {
        $title = e((string) ($data['label'] ?? 'Totals'));
        $subtotal = e(MoneyFormatter::formatMinor($doc->subtotal_minor, $doc->currency));
        $tax = e(MoneyFormatter::formatMinor($doc->tax_amount_minor, $doc->currency));
        $discount = e(MoneyFormatter::formatMinor($doc->discount_amount_minor, $doc->currency));
        $total = e(MoneyFormatter::formatMinor($doc->total_minor, $doc->currency));

        return <<<HTML
        <section class="doc-block doc-totals">
            <h2>{$title}</h2>
            <dl>
                <div><dt>Subtotal</dt><dd>{$subtotal}</dd></div>
                <div><dt>Tax</dt><dd>{$tax}</dd></div>
                <div><dt>Discount</dt><dd>{$discount}</dd></div>
                <div class="doc-grand-total"><dt>Total</dt><dd>{$total}</dd></div>
            </dl>
        </section>
        HTML;
    }

    private function renderNotesTerms(Doc $doc, array $data): string
    {
        $notesLabel = e((string) ($data['notes_label'] ?? 'Notes'));
        $termsLabel = e((string) ($data['terms_label'] ?? 'Terms'));
        $notes = filled($doc->notes) ? '<div><h2>' . $notesLabel . '</h2><p>' . e((string) $doc->notes) . '</p></div>' : '';
        $terms = filled($doc->terms) ? '<div><h2>' . $termsLabel . '</h2><p>' . e((string) $doc->terms) . '</p></div>' : '';

        if ($notes === '' && $terms === '') {
            return '';
        }

        return '<section class="doc-block doc-notes-terms">' . $notes . $terms . '</section>';
    }

    private function renderSignaturePayment(array $data): string
    {
        $label = e((string) ($data['label'] ?? 'Signature / Payment Instructions'));
        $body = e((string) ($data['body'] ?? ''));

        if ($body === '') {
            return '';
        }

        return "<section class=\"doc-block\"><h2>{$label}</h2><p>{$body}</p></section>";
    }

    private function renderFooter(array $data): string
    {
        $text = e((string) ($data['text'] ?? 'Thank you for your business.'));

        return "<footer class=\"doc-footer\">{$text}</footer>";
    }

    /** @return RelatedDocTemplate|null */
    private function resolveTemplate(Doc $doc): ?Model
    {
        if ($doc->doc_template_id !== null) {
            return $this->templateQueryForDoc($doc)->where('doc_type', $doc->doc_type)->whereKey($doc->doc_template_id)->first();
        }

        return $this->templateQueryForDoc($doc)
            ->where('is_default', true)
            ->where('doc_type', $doc->doc_type)
            ->first();
    }

    /**
     * @return Builder<RelatedDocTemplate>
     */
    private function templateQueryForDoc(Doc $doc): Builder
    {
        $query = app(TemplateRelationModel::class)->template()::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $owner = OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id);

        return $query->forOwner($owner, (bool) config('docs.owner.include_global', false));
    }

    private function resolveStorageDisk(string $docType): string
    {
        $key = DocTypeKey::sanitize($docType);

        if ($key !== null) {
            $disk = config("docs.types.{$key}.storage.disk");

            if (is_string($disk) && $disk !== '') {
                return $disk;
            }
        }

        return config('docs.storage.disk', 'local');
    }
}
