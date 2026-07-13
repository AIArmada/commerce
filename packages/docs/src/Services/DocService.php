<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Models\DocVersion;
use AIArmada\Docs\Numbering\NumberStrategyRegistry;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Core document management service.
 *
 * Handles document creation, updates, PDF generation, payments, versioning, and conversions.
 */
final class DocService implements DocServiceInterface
{
    public function __construct(
        protected NumberStrategyRegistry $numberRegistry,
        protected SequenceManager $sequenceManager,
    ) {}

    /**
     * Generate a document number for the given type.
     */
    public function generateNumber(string $docType = 'invoice'): string
    {
        return $this->numberRegistry->generate($docType);
    }

    /**
     * Resolve the storage disk for a document type.
     */
    public function resolveStorageDiskForDocType(string $docType): string
    {
        return $this->resolveStorageDisk($docType);
    }

    /**
     * Create a new document from DocData DTO.
     */
    public function create(DocData $data): Doc
    {
        $docType = $data->docType ?? 'invoice';

        // Generate doc number if not provided
        $docNumber = $data->docNumber ?? $this->generateNumber($docType);

        // Resolve current owner
        $owner = $this->resolveOwner();

        $template = $this->resolveTemplateSelection($docType, $data->docTemplateId, $data->templateSlug);

        if ($template instanceof DocTemplate) {
            app(DocRenderService::class)->validateDocPayload($template, $data->body, $data->items);
        }

        $currency = mb_strtoupper($data->currency ?? (string) $this->resolveDefault($docType, 'currency', 'MYR'));
        $calculatedSubtotalMinor = $this->calculateSubtotalMinor($data->items, $currency);
        $subtotalMinor = $data->subtotalMinor ?? $calculatedSubtotalMinor;
        $taxRateBasisPoints = $data->taxRateBasisPoints ?? (int) config('docs.defaults.tax_rate_basis_points', 0);
        $this->assertBasisPoints($taxRateBasisPoints);
        $taxAmountMinor = $data->taxAmountMinor ?? $this->applyBasisPoints($subtotalMinor, $taxRateBasisPoints);
        $discountAmountMinor = $data->discountAmountMinor ?? 0;
        $totalMinor = $data->totalMinor ?? max(0, $subtotalMinor + $taxAmountMinor - $discountAmountMinor);
        $this->assertNonNegativeAmounts($subtotalMinor, $taxAmountMinor, $discountAmountMinor, $totalMinor);

        // Merge metadata with pdf options (if provided)
        $metadata = $data->metadata ?? [];
        if ($data->pdfOptions !== null) {
            $metadata['pdf'] = array_merge($metadata['pdf'] ?? [], $data->pdfOptions);
        }

        // Determine status
        $status = $data->status ?? Draft::class;

        if ($status instanceof DocStatus) {
            $status = $status::class;
        }

        $statusClass = DocStatus::resolveStateClassFor($status);

        // Only set due_date for payable statuses (not for PAID, CANCELLED, REFUNDED)
        $dueDate = $data->dueDate;
        if ($dueDate === null && DocStatus::fromString($statusClass)->isPayable()) {
            $dueDays = (int) $this->resolveDefault($docType, 'due_days', 30);
            $dueDate = CarbonImmutable::now()->addDays($dueDays);
        }

        // Build doc data with owner columns if enabled
        $docData = [
            'doc_number' => $docNumber,
            'doc_type' => $docType,
            'doc_template_id' => $template?->id,
            'docable_type' => $data->docableType,
            'docable_id' => $data->docableId,
            'status' => $statusClass,
            'issue_date' => $data->issueDate ?? CarbonImmutable::now(),
            'due_date' => $dueDate,
            'subtotal_minor' => $subtotalMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'discount_amount_minor' => $discountAmountMinor,
            'total_minor' => $totalMinor,
            'currency' => $currency,
            'body' => $data->body,
            'notes' => $data->notes,
            'terms' => $data->terms,
            'customer_data' => $data->customerData,
            'company_data' => $data->companyData ?? config('docs.company'),
            'items' => $data->items,
            'metadata' => $metadata,
        ];

        // Create doc
        $doc = new Doc($docData);

        if ($owner !== null && (bool) config('docs.owner.auto_assign_on_create', true)) {
            $doc->owner_type = $owner->getMorphClass();
            $doc->owner_id = (string) $owner->getKey();
        }

        $doc->save();

        // Load relationships
        $doc->loadMissing(['template', 'docable']);

        // Generate PDF if requested
        if ($data->generatePdf ?? false) {
            $this->generatePdf($doc);
        }

        return $doc;
    }

    /**
     * Create a new document from array data with DocType enum.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromType(DocType $type, array $data, ?Model $owner = null): Doc
    {
        return DB::transaction(function () use ($type, $data, $owner): Doc {
            // Generate document number
            $docNumber = $this->sequenceManager->generate($type, $owner);

            $docData = array_merge($data, [
                'doc_number' => $docNumber,
                'doc_type' => $type->value,
                'status' => Draft::class,
                'issue_date' => $data['issue_date'] ?? CarbonImmutable::now(),
            ]);

            // Calculate totals if items provided
            if (isset($data['items'])) {
                $totals = $this->calculateTotals(
                    $data['items'],
                    (int) ($data['discount_amount_minor'] ?? 0),
                    isset($data['currency']) ? (string) $data['currency'] : null
                );
                $docData = array_merge($docData, $totals);
            }

            $template = $this->resolveTemplateSelection(
                $type->value,
                isset($docData['doc_template_id']) ? (string) $docData['doc_template_id'] : null,
                isset($docData['template_slug']) ? (string) $docData['template_slug'] : null,
            );

            if ($template instanceof DocTemplate) {
                $docData['doc_template_id'] = $template->id;
                app(DocRenderService::class)->validateDocPayload(
                    $template,
                    isset($docData['body']) && is_array($docData['body']) ? $docData['body'] : null,
                    isset($docData['items']) && is_array($docData['items']) ? $docData['items'] : [],
                );
            }

            $doc = new Doc($docData);

            if ($owner !== null) {
                $doc->owner_type = $owner->getMorphClass();
                $doc->owner_id = (string) $owner->getKey();
            }

            $doc->save();

            // Create initial version
            $this->createVersion($doc, 'Initial creation');

            return $doc;
        });
    }

    /**
     * Update a document and create a version snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Doc $doc, array $data): Doc
    {
        return DB::transaction(function () use ($doc, $data): Doc {
            $effectiveDocType = isset($data['doc_type']) && is_string($data['doc_type']) && $data['doc_type'] !== ''
                ? $data['doc_type']
                : $doc->doc_type;

            $templateId = array_key_exists('doc_template_id', $data)
                ? (filled($data['doc_template_id'] ?? null) ? (string) $data['doc_template_id'] : null)
                : $doc->doc_template_id;

            $template = $this->resolveTemplateSelection($effectiveDocType, $templateId);

            if ($template instanceof DocTemplate) {
                app(DocRenderService::class)->validateDocPayload(
                    $template,
                    $data['body'] ?? $doc->body,
                    $data['items'] ?? ($doc->items ?? []),
                );
            }

            // Calculate totals if items changed
            if (isset($data['items'])) {
                $totals = $this->calculateTotals(
                    $data['items'],
                    (int) ($data['discount_amount_minor'] ?? $doc->discount_amount_minor),
                    isset($data['currency']) ? (string) $data['currency'] : $doc->currency
                );
                $data = array_merge($data, $totals);
            }

            $doc->update($data);

            // Create version snapshot
            $this->createVersion($doc, 'Document updated');

            return $doc->fresh() ?? $doc;
        });
    }

    /**
     * Convert a document to another type.
     */
    public function convert(Doc $source, DocType $targetType, ?Model $owner = null): Doc
    {
        $sourceType = $source->doc_type instanceof DocType
            ? $source->doc_type
            : DocType::tryFrom($source->doc_type);

        // Validate conversion is allowed
        $allowedSources = $targetType->getConversionSources();
        if ($sourceType && ! in_array($sourceType, $allowedSources, true)) {
            throw new InvalidArgumentException(
                "Cannot convert {$sourceType->label()} to {$targetType->label()}"
            );
        }

        // Create new document from source
        return $this->createFromType($targetType, [
            'docable_type' => $source->docable_type,
            'docable_id' => $source->docable_id,
            'doc_template_id' => $source->doc_template_id,
            'due_date' => $source->due_date,
            'currency' => $source->currency,
            'body' => $source->body,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'customer_data' => $source->customer_data,
            'company_data' => $source->company_data,
            'items' => $source->items,
            'metadata' => array_merge($source->metadata ?? [], [
                'converted_from' => [
                    'doc_id' => $source->id,
                    'doc_number' => $source->doc_number,
                    'doc_type' => $source->doc_type,
                ],
            ]),
        ], $owner);
    }

    /**
     * Record a payment against a document.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function recordPayment(Doc $doc, array $paymentData): DocPayment
    {
        return DB::transaction(function () use ($doc, $paymentData): DocPayment {
            $lockedDoc = Doc::query()
                ->withoutOwnerScope()
                ->whereKey($doc->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $paymentCurrency = mb_strtoupper((string) ($paymentData['currency'] ?? $lockedDoc->currency));

            if ($paymentCurrency !== mb_strtoupper($lockedDoc->currency)) {
                throw new InvalidArgumentException('Payment currency must match the document currency.');
            }

            if (! isset($paymentData['amount_minor']) || ! is_int($paymentData['amount_minor']) || $paymentData['amount_minor'] <= 0) {
                throw new InvalidArgumentException('Payment amount_minor must be a positive integer.');
            }

            $totalPaidBefore = (int) $lockedDoc->payments()->sum('amount_minor');
            $remainingMinor = $lockedDoc->total_minor - $totalPaidBefore;

            if ($paymentData['amount_minor'] > $remainingMinor) {
                throw new InvalidArgumentException('Payment amount_minor cannot exceed the outstanding document balance.');
            }

            $payment = $lockedDoc->payments()->make(array_merge($paymentData, [
                'paid_at' => $paymentData['paid_at'] ?? CarbonImmutable::now(),
                'currency' => $paymentCurrency,
            ]));

            if (config('docs.owner.enabled', false)) {
                $payment->owner_type = $lockedDoc->owner_type;
                $payment->owner_id = $lockedDoc->owner_id;
            }

            $payment->save();

            // Update document status based on payments
            $totalPaid = $totalPaidBefore + $payment->amount_minor;
            $docTotal = $lockedDoc->total_minor;

            if ($totalPaid === $docTotal) {
                $lockedDoc->markAsPaid("Payment recorded: {$payment->amount_minor} {$payment->currency} minor units");
            } elseif ($totalPaid > 0) {
                $lockedDoc->update(['status' => PartiallyPaid::class]);
                $statusHistory = $lockedDoc->statusHistories()->make([
                    'status' => PartiallyPaid::class,
                    'notes' => "Partial payment recorded: {$payment->amount_minor} {$payment->currency} minor units",
                    'created_at' => CarbonImmutable::now(),
                ]);

                if (config('docs.owner.enabled', false)) {
                    $statusHistory->owner_type = $lockedDoc->owner_type;
                    $statusHistory->owner_id = $lockedDoc->owner_id;
                }

                $statusHistory->save();
            }

            return $payment;
        });
    }

    /**
     * Clone a document.
     */
    public function clone(Doc $source, ?Model $owner = null): Doc
    {
        $val = $source->doc_type;
        $type = ($val instanceof DocType ? $val : DocType::tryFrom($val)) ?? DocType::Invoice;

        return $this->createFromType($type, [
            'docable_type' => $source->docable_type,
            'docable_id' => $source->docable_id,
            'doc_template_id' => $source->doc_template_id,
            'due_date' => CarbonImmutable::now()->addDays(config('docs.defaults.due_days', 30)),
            'currency' => $source->currency,
            'body' => $source->body,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'customer_data' => $source->customer_data,
            'company_data' => $source->company_data,
            'items' => $source->items,
            'metadata' => array_merge($source->metadata ?? [], [
                'cloned_from' => $source->id,
            ]),
        ], $owner);
    }

    /**
     * Create a version snapshot.
     */
    public function createVersion(Doc $doc, ?string $summary = null): DocVersion
    {
        $nextVersion = $doc->versions()->max('version_number') + 1;

        $version = $doc->versions()->make([
            'version_number' => $nextVersion,
            'snapshot' => $doc->toArray(),
            'change_summary' => $summary,
            'changed_by' => auth()->id(),
            'created_at' => CarbonImmutable::now(),
        ]);

        if (config('docs.owner.enabled', false)) {
            $version->owner_type = $doc->owner_type;
            $version->owner_id = $doc->owner_id;
        }

        $version->save();

        return $version;
    }

    /**
     * Generate a PDF for a document.
     *
     * @return string The relative path to the stored PDF (or raw PDF content if $save is false)
     */
    public function generatePdf(Doc $doc, bool $save = true): string
    {
        $renderer = app(DocRenderService::class);

        return $save
            ? $renderer->storePdf($doc)
            : $renderer->renderPdf($doc);
    }

    /**
     * Download or retrieve PDF path for a document.
     *
     * @return string The relative path to the PDF
     */
    public function downloadPdf(Doc $doc): string
    {
        $docType = $doc->doc_type ?? 'invoice';

        if ($doc->pdf_path && Storage::disk($this->resolveStorageDisk($docType))->exists($doc->pdf_path)) {
            return $doc->pdf_path;
        }

        return $this->generatePdf($doc);
    }

    /**
     * Mark a document as sent (typically after emailing).
     */
    public function markAsSent(Doc $doc, ?string $notes = null): void
    {
        $doc->markAsSent($notes);
    }

    /**
     * Update a document's status with audit trail.
     */
    public function updateStatus(Doc $doc, DocStatus | string $status, ?string $notes = null): void
    {
        $oldStatus = $doc->status;
        $statusClass = DocStatus::resolveStateClassFor($status, $doc);

        $doc->update(['status' => $statusClass]);

        // Record status change
        $statusHistory = $doc->statusHistories()->make([
            'status' => $statusClass,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::labelFor($statusClass, $doc),
            'created_at' => CarbonImmutable::now(),
        ]);

        if (config('docs.owner.enabled', false)) {
            $statusHistory->owner_type = $doc->owner_type;
            $statusHistory->owner_id = $doc->owner_id;
        }

        $statusHistory->save();
    }

    /**
     * Calculate document totals from minor-unit item values.
     *
     * Every item must use integer `quantity`, `unit_price_minor`, and optional
     * `tax_amount_minor`. Legacy major-unit aliases are rejected.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal_minor: int, tax_amount_minor: int, total_minor: int}
     */
    public function calculateTotals(array $items, int $discountAmountMinor = 0, ?string $currency = null): array
    {
        if ($discountAmountMinor < 0) {
            throw new InvalidArgumentException('discount_amount_minor must not be negative.');
        }

        $currency = $currency !== null ? mb_strtoupper($currency) : null;
        $subtotalMinor = 0;
        $taxAmountMinor = 0;

        foreach ($items as $index => $item) {
            $this->assertMinorUnitItem($item, $index, $currency);

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitPriceMinor = (int) $item['unit_price_minor'];
            $itemTaxMinor = (int) ($item['tax_amount_minor'] ?? 0);

            $subtotalMinor += $quantity * $unitPriceMinor;
            $taxAmountMinor += $itemTaxMinor;
        }

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_amount_minor' => $taxAmountMinor,
            'total_minor' => max(0, $subtotalMinor + $taxAmountMinor - $discountAmountMinor),
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function calculateSubtotalMinor(array $items, string $currency): int
    {
        return $this->calculateTotals($items, 0, $currency)['subtotal_minor'];
    }

    /** @param array<string, mixed> $item */
    private function assertMinorUnitItem(array $item, int $index, ?string $currency): void
    {
        foreach (['price', 'unit_price', 'tax_amount'] as $legacyKey) {
            if (array_key_exists($legacyKey, $item)) {
                throw new InvalidArgumentException(sprintf(
                    'Document item %d uses removed major-unit field `%s`; use the corresponding `*_minor` integer field.',
                    $index,
                    $legacyKey,
                ));
            }
        }

        $quantity = $item['quantity'] ?? 1;

        if (! is_int($quantity) || $quantity <= 0) {
            throw new InvalidArgumentException(sprintf('Document item %d quantity must be a positive integer.', $index));
        }

        if (! array_key_exists('unit_price_minor', $item) || ! is_int($item['unit_price_minor']) || $item['unit_price_minor'] < 0) {
            throw new InvalidArgumentException(sprintf('Document item %d unit_price_minor must be a non-negative integer.', $index));
        }

        if (isset($item['tax_amount_minor']) && (! is_int($item['tax_amount_minor']) || $item['tax_amount_minor'] < 0)) {
            throw new InvalidArgumentException(sprintf('Document item %d tax_amount_minor must be a non-negative integer.', $index));
        }

        if (isset($item['currency']) && $currency !== null && mb_strtoupper((string) $item['currency']) !== $currency) {
            throw new InvalidArgumentException(sprintf('Document item %d currency does not match document currency.', $index));
        }
    }

    private function applyBasisPoints(int $amountMinor, int $basisPoints): int
    {
        return intdiv(($amountMinor * $basisPoints) + 5_000, 10_000);
    }

    private function assertBasisPoints(int $basisPoints): void
    {
        if ($basisPoints < 0 || $basisPoints > 100_000) {
            throw new InvalidArgumentException('tax_rate_basis_points must be between 0 and 100000.');
        }
    }

    private function assertNonNegativeAmounts(int ...$amounts): void
    {
        foreach ($amounts as $amount) {
            if ($amount < 0) {
                throw new InvalidArgumentException('Document monetary amounts must be non-negative minor-unit integers.');
            }
        }
    }

    protected function resolveStorageDisk(string $docType): string
    {
        return config("docs.types.{$docType}.storage.disk")
            ?? config('docs.storage.disk', 'local');
    }

    protected function resolveDefault(string $docType, string $key, mixed $fallback = null): mixed
    {
        return config("docs.types.{$docType}.defaults.{$key}", config("docs.defaults.{$key}", $fallback));
    }

    protected function resolveTemplateSelection(string $docType, ?string $templateId = null, ?string $templateSlug = null): ?DocTemplate
    {
        $query = $this->getTemplateQuery()->where('doc_type', $docType);

        if ($templateId !== null && $templateId !== '') {
            $template = $query->find($templateId);

            if (! $template instanceof DocTemplate) {
                throw ValidationException::withMessages([
                    'doc_template_id' => __('Invalid template selection for this document type.'),
                ]);
            }

            return $template;
        }

        if ($templateSlug !== null && $templateSlug !== '') {
            $template = $query->where('slug', $templateSlug)->first();

            if (! $template instanceof DocTemplate) {
                throw ValidationException::withMessages([
                    'template_slug' => __('Invalid template selection for this document type.'),
                ]);
            }

            return $template;
        }

        return $query
            ->where('is_default', true)
            ->first();
    }

    /**
     * Resolve the current owner from the configured resolver.
     */
    protected function resolveOwner(): ?Model
    {
        if (! config('docs.owner.enabled', false)) {
            return null;
        }

        return OwnerContext::resolve();
    }

    /**
     * Get template query builder with owner scoping applied.
     *
     * @return Builder<DocTemplate>
     */
    protected function getTemplateQuery(): Builder
    {
        $query = DocTemplate::query();

        if (! config('docs.owner.enabled', false)) {
            return $query;
        }

        $owner = $this->resolveOwner();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        return $query->forOwner($owner, $includeGlobal);
    }
}
