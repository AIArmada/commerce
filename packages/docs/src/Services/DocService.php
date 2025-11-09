<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class DocService
{
    /**
     * Normalize a template view name into the canonical 'docs::templates.<slug>' form.
     */
    protected function normalizeViewName(string $viewName): string
    {
        $viewName = trim($viewName);

        // Already correct
        if (str_starts_with($viewName, 'docs::templates.')) {
            return $viewName;
        }

        // If it has the docs:: prefix but missing templates.
        if (str_starts_with($viewName, 'docs::')) {
            $suffix = substr($viewName, strlen('docs::')) ?: '';
            if ($suffix === '') {
                return 'docs::templates.doc-default';
            }
            if (str_starts_with($suffix, 'templates.')) {
                return 'docs::' . $suffix; // becomes docs::templates.<slug>
            }
            return 'docs::templates.' . $suffix; // ensure templates prefix
        }

        // Dot notation like docs.templates.slug
        if (str_starts_with($viewName, 'docs.templates.')) {
            $slug = substr($viewName, strlen('docs.templates.')) ?: 'doc-default';
            return 'docs::templates.' . $slug;
        }

        // Starting with templates.
        if (str_starts_with($viewName, 'templates.')) {
            $slug = substr($viewName, strlen('templates.')) ?: 'doc-default';
            return 'docs::templates.' . $slug;
        }

        // Fallback plain slug
        return 'docs::templates.' . $viewName;
    }
    public function generateDocNumber(string $docType = 'invoice'): string
    {
        $config = config("docs.types.{$docType}.number_format");
        $prefix = $config['prefix'] ?? 'DOC';
        $yearFormat = $config['year_format'] ?? 'y';
        $separator = $config['separator'] ?? '-';
        $suffixLength = $config['suffix_length'] ?? 6;

        $year = now()->format($yearFormat);
        $suffix = mb_strtoupper(mb_substr(uniqid(), -$suffixLength));

        return "{$prefix}{$year}{$separator}{$suffix}";
    }

    public function createDoc(DocData $data): Doc
    {
        $docType = $data->docType ?? 'invoice';

        // Generate doc number if not provided
        $docNumber = $data->docNumber ?? $this->generateDocNumber($docType);

        // Get template
        $template = null;
        if ($data->docTemplateId) {
            $template = DocTemplate::find($data->docTemplateId);
        } elseif ($data->templateSlug) {
            $template = DocTemplate::where('slug', $data->templateSlug)->first();
        }

        if (! $template) {
            $template = DocTemplate::where('is_default', true)
                ->where('doc_type', $docType)
                ->first();
        }

        // Calculate totals
        $subtotal = $this->calculateSubtotal($data->items);
        $taxAmount = $data->taxAmount ?? ($subtotal * ($data->taxRate ?? 0));
        $discountAmount = $data->discountAmount ?? 0;
        $total = $subtotal + $taxAmount - $discountAmount;

        // Create doc
        $doc = Doc::create([
            'doc_number' => $docNumber,
            'doc_type' => $docType,
            'doc_template_id' => $template?->id,
            'docable_type' => $data->docableType,
            'docable_id' => $data->docableId,
            'status' => $data->status ?? DocStatus::DRAFT,
            'issue_date' => $data->issueDate ?? now(),
            'due_date' => $data->dueDate ?? now()->addDays(config("docs.types.{$docType}.defaults.due_days", 30)),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'currency' => $data->currency ?? config("docs.types.{$docType}.defaults.currency", 'MYR'),
            'notes' => $data->notes,
            'terms' => $data->terms,
            'customer_data' => $data->customerData,
            'company_data' => $data->companyData ?? config('docs.company'),
            'items' => $data->items,
            'metadata' => $data->metadata,
        ]);

        // Generate PDF if requested
        if ($data->generatePdf ?? false) {
            $this->generatePdf($doc);
        }

        return $doc;
    }

    public function generatePdf(Doc $doc, bool $save = true): string
    {
        // Load the polymorphic relationship to access ticket/order data
        $doc->loadMissing('docable');

        $docType = $doc->doc_type ?? 'invoice';
        $template = $doc->template ?? DocTemplate::where('is_default', true)
            ->where('doc_type', $docType)
            ->first();
        $viewName = $template->view_name ?? config("docs.types.{$docType}.default_template", "{$docType}-default");
        $resolvedView = $this->normalizeViewName($viewName);

        $pdf = Pdf::view($resolvedView, [
            'doc' => $doc,
        ])
            ->format(config('docs.pdf.format', 'a4'))
            ->orientation(config('docs.pdf.orientation', 'portrait'))
            ->margins(
                config('docs.pdf.margin.top', 10),
                config('docs.pdf.margin.right', 10),
                config('docs.pdf.margin.bottom', 10),
                config('docs.pdf.margin.left', 10)
            );

        if ($save) {
            $path = $this->generatePdfPath($doc);
            $disk = config("docs.types.{$docType}.storage.disk", 'local');

            Storage::disk($disk)->put($path, $pdf->getBrowsershot()->pdf());

            $doc->update(['pdf_path' => $path]);

            return Storage::disk($disk)->url($path);
        }

        return $pdf->getBrowsershot()->pdf();
    }

    public function downloadPdf(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';

        if ($doc->pdf_path && Storage::disk(config("docs.types.{$docType}.storage.disk", 'local'))->exists($doc->pdf_path)) {
            return Storage::disk(config("docs.types.{$docType}.storage.disk", 'local'))->url($doc->pdf_path);
        }

        return $this->generatePdf($doc);
    }

    public function emailDoc(Doc $doc, string $email): void
    {
        // This would integrate with your mail system
        // Implementation depends on your mail setup
        $doc->markAsSent();
    }

    public function updateDocStatus(Doc $doc, DocStatus $status, ?string $notes = null): void
    {
        $oldStatus = $doc->status;

        $doc->update(['status' => $status]);

        // Record status change
        $doc->statusHistories()->create([
            'status' => $status,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to {$status->label()}",
        ]);
    }

    /**
     * Calculate subtotal from items
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function calculateSubtotal(array $items): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            $subtotal += $quantity * $price;
        }

        return $subtotal;
    }

    protected function generatePdfPath(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';
        $basePath = config("docs.types.{$docType}.storage.path", 'docs');
        $filename = Str::slug($doc->doc_number).'.pdf';

        return "{$basePath}/{$filename}";
    }
}
