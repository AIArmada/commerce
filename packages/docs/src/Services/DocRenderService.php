<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\RichContentRendererInterface;
use AIArmada\Docs\DataObjects\ShareLinkData;
use AIArmada\Docs\Enums\DocMergeTag;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Enums\RenderAudience;
use AIArmada\Docs\Enums\ShareLinkAction;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocShareLink;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class DocRenderService
{
    public function __construct(
        private readonly RichContentRendererInterface $richContentRenderer,
    ) {}

    public function renderHtml(Doc $doc, RenderAudience $audience): HtmlString
    {
        $doc->loadMissing(['template', 'docable']);

        $template = $this->resolveTemplate($doc);
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
        $html = $this->renderHtml($doc, RenderAudience::Pdf)->toHtml();
        $opts = $this->resolvePdfOptions($doc);

        $pdf = Pdf::html($html)
            ->format($opts['format'])
            ->orientation($opts['orientation'])
            ->margins(
                $opts['margin']['top'],
                $opts['margin']['right'],
                $opts['margin']['bottom'],
                $opts['margin']['left']
            );

        if (! empty($opts['full_bleed'])) {
            $pdf->margins(0, 0, 0, 0);
        }

        if (! empty($opts['print_background'])) {
            $pdf->withBrowsershot(static function ($browsershot): void {
                $browsershot->showBackground();
            });
        }

        return $pdf->generatePdfContent();
    }

    public function storePdf(Doc $doc): string
    {
        $path = $this->generatePdfPath($doc);
        $disk = $this->resolveStorageDisk($doc->doc_type ?? 'invoice');

        Storage::disk($disk)->put($path, $this->renderPdf($doc));

        $doc->update(['pdf_path' => $path]);

        return $path;
    }

    public function createShareLink(Doc $doc, ShareLinkData $data): DocShareLink
    {
        $plainToken = Str::random(48);

        $shareLink = new DocShareLink([
            'doc_id' => $doc->getKey(),
            'token_hash' => hash('sha256', $plainToken),
            'allowed_actions' => $data->allowedActionValues(),
            'expires_at' => $data->expiresAt ?? CarbonImmutable::now()->addDays((int) config('docs.sharing.default_expiry_days', 30)),
        ]);

        if (config('docs.owner.enabled', false)) {
            $shareLink->owner_type = $doc->owner_type;
            $shareLink->owner_id = $doc->owner_id;
        }

        $shareLink->save();
        $shareLink->setRelation('doc', $doc);
        $shareLink->setPlainToken($plainToken);

        return $shareLink;
    }

    public function resolveShareLink(string $plainToken, ShareLinkAction $action): DocShareLink
    {
        /** @var DocShareLink|null $shareLink */
        $shareLink = DocShareLink::query()
            ->withoutOwnerScope()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $shareLink instanceof DocShareLink) {
            throw new NotFoundHttpException('Document link not found.');
        }

        if ($shareLink->isRevoked() || $shareLink->isExpired() || ! $shareLink->allows($action)) {
            throw new NotFoundHttpException('Document link not found.');
        }

        try {
            $owner = OwnerContext::fromTypeAndId($shareLink->owner_type, $shareLink->owner_id);
        } catch (Throwable) {
            throw new NotFoundHttpException('Document link not found.');
        }

        return OwnerContext::withOwner($owner, function () use ($shareLink): DocShareLink {
            $doc = Doc::query()->find($shareLink->doc_id);

            if (! $doc instanceof Doc) {
                throw new NotFoundHttpException('Document link not found.');
            }

            $shareLink->setRelation('doc', $doc);
            $shareLink->markAccessed();

            return $shareLink;
        });
    }

    public function validateDocPayload(DocTemplate $template, ?array $body, array $items): void
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

    /**
     * @return array{format: string, orientation: string, margin: array{top: int, right: int, bottom: int, left: int}, full_bleed: bool, print_background: bool}
     */
    private function resolvePdfOptions(Doc $doc): array
    {
        $template = $this->resolveTemplate($doc);
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

        return [
            'format' => (string) ($options['format'] ?? $defaults['format']),
            'orientation' => (string) ($options['orientation'] ?? $defaults['orientation']),
            'margin' => [
                'top' => (int) data_get($options, 'margin.top', $defaults['margin']['top']),
                'right' => (int) data_get($options, 'margin.right', $defaults['margin']['right']),
                'bottom' => (int) data_get($options, 'margin.bottom', $defaults['margin']['bottom']),
                'left' => (int) data_get($options, 'margin.left', $defaults['margin']['left']),
            ],
            'full_bleed' => (bool) ($options['full_bleed'] ?? $defaults['full_bleed']),
            'print_background' => (bool) ($options['print_background'] ?? $defaults['print_background']),
        ];
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
        $currency = e((string) $doc->currency);
        $total = $this->money((float) $doc->total);

        return <<<HTML
        <section class="doc-block doc-header">
            <div>
                <h1>{$label}</h1>
                <p>{$docNumber}</p>
            </div>
            <strong>{$currency} {$total}</strong>
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
        $currency = e((string) $doc->currency);

        if ($items === []) {
            return '';
        }

        $title = e((string) ($data['label'] ?? 'Items'));
        $rows = collect($items)
            ->map(function (array $item) use ($currency): string {
                $quantity = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['price'] ?? $item['unit_price'] ?? 0);
                $lineTotal = $quantity * $price;
                $name = e((string) ($item['name'] ?? $item['description'] ?? 'Item'));
                $description = filled($item['description'] ?? null) && isset($item['name'])
                    ? '<small>' . e((string) $item['description']) . '</small>'
                    : '';

                return <<<HTML
                <tr>
                    <td><strong>{$name}</strong>{$description}</td>
                    <td class="doc-number">{$quantity}</td>
                    <td class="doc-number">{$currency} {$this->money($price)}</td>
                    <td class="doc-number">{$currency} {$this->money($lineTotal)}</td>
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
        $currency = e((string) $doc->currency);

        return <<<HTML
        <section class="doc-block doc-totals">
            <h2>{$title}</h2>
            <dl>
                <div><dt>Subtotal</dt><dd>{$currency} {$this->money((float) $doc->subtotal)}</dd></div>
                <div><dt>Tax</dt><dd>{$currency} {$this->money((float) $doc->tax_amount)}</dd></div>
                <div><dt>Discount</dt><dd>{$currency} {$this->money((float) $doc->discount_amount)}</dd></div>
                <div class="doc-grand-total"><dt>Total</dt><dd>{$currency} {$this->money((float) $doc->total)}</dd></div>
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

    private function resolveTemplate(Doc $doc): ?DocTemplate
    {
        if ($doc->relationLoaded('template') && $doc->template instanceof DocTemplate) {
            return $doc->template;
        }

        if ($doc->doc_template_id !== null) {
            return $doc->template()->first();
        }

        $query = DocTemplate::query();

        if (config('docs.owner.enabled', false)) {
            $query = $this->scopeTemplateQueryToDoc($query, $doc);
        }

        return $query
            ->where('is_default', true)
            ->where('doc_type', $doc->doc_type)
            ->first();
    }

    /**
     * @param  Builder<DocTemplate>  $query
     * @return Builder<DocTemplate>
     */
    private function scopeTemplateQueryToDoc(Builder $query, Doc $doc): Builder
    {
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if ($doc->owner_type !== null && $doc->owner_id !== null) {
            return $query->where(function (Builder $builder) use ($doc, $includeGlobal): void {
                $builder->where('owner_type', $doc->owner_type)
                    ->where('owner_id', $doc->owner_id);

                if ($includeGlobal) {
                    $builder->orWhere(fn (Builder $inner): Builder => $inner->whereNull('owner_type')->whereNull('owner_id'));
                }
            });
        }

        return $query->whereNull('owner_type')->whereNull('owner_id');
    }

    private function resolveStorageDisk(string $docType): string
    {
        return config("docs.types.{$docType}.storage.disk")
            ?? config('docs.storage.disk', 'local');
    }

    private function resolveStoragePath(string $docType): string
    {
        return config("docs.types.{$docType}.storage.path")
            ?? config('docs.storage.path', 'docs');
    }

    private function generatePdfPath(Doc $doc): string
    {
        $basePath = mb_trim($this->resolveStoragePath($doc->doc_type ?? 'invoice'), '/');
        $filename = $this->normalizePdfFilename($doc);

        return $basePath === '' ? $filename : "{$basePath}/{$filename}";
    }

    private function normalizePdfFilename(Doc $doc): string
    {
        $raw = str_replace(['/', '\\'], '-', (string) ($doc->doc_number ?: $doc->getKey()));
        $sanitized = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $raw);
        $sanitized = mb_trim($sanitized, " .-_/\t\n\r\0\x0B");

        while (str_contains($sanitized, '..')) {
            $sanitized = str_replace('..', '.', $sanitized);
        }

        $sanitized = str_replace('.', '-', $sanitized);
        $sanitized = (string) preg_replace('/[-_]{2,}/', '-', $sanitized);
        $sanitized = mb_trim($sanitized, '-_ ');

        if ($sanitized === '') {
            $sanitized = (string) $doc->getKey();
        }

        return Str::limit($sanitized, 120, '') . '.pdf';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
