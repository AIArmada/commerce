<?php

declare(strict_types=1);

namespace AIArmada\Docs\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocServiceInterface;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Models\DocVersion;
use AIArmada\Docs\Numbering\DocumentNumberRegistry;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Sent;
use AIArmada\Docs\Support\DocTypeKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
        protected DocumentNumberRegistry $numberRegistry,
        protected SequenceManager $sequenceManager,
        protected DocTotals $totals,
        protected DocPaymentRecorder $paymentRecorder,
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
     *
     * Numbers come from the atomic per-owner sequence unless an explicit
     * doc number is provided. Explicit totals must match the items-derived
     * values; mismatches are rejected instead of persisted.
     */
    public function create(DocData $data): Doc
    {
        $docType = $data->docType ?? 'invoice';
        $type = DocType::tryFrom($docType);

        if ($type === null) {
            throw new InvalidArgumentException("Unknown document type [{$docType}].");
        }

        $owner = $this->resolveOwner();

        return DB::transaction(function () use ($data, $type, $docType, $owner): Doc {
            $docNumber = $data->docNumber ?? $this->sequenceManager->generate($type, $owner);
            $this->assertDocNumberAvailable($docNumber, $owner);

            $template = $this->resolveTemplateSelection($docType, $data->docTemplateId, $data->templateSlug);

            if ($template instanceof DocTemplate) {
                app(DocRenderService::class)->validateDocPayload($template, $data->body, $data->items);
            }

            $currency = $this->normalizeCurrency($data->currency ?? $this->resolveDefault($docType, 'currency', 'MYR'));
            $calculatedSubtotalMinor = $this->calculateSubtotalMinor($data->items, $currency);
            $taxRateBasisPoints = $data->taxRateBasisPoints ?? $this->resolveConfiguredTaxRateBasisPoints();
            $this->assertBasisPoints($taxRateBasisPoints);
            $calculatedTaxMinor = $this->applyBasisPoints($calculatedSubtotalMinor, $taxRateBasisPoints);
            $discountAmountMinor = $data->discountAmountMinor ?? 0;

            if ($data->items !== []) {
                $this->assertMatchesComputed('subtotal_minor', $data->subtotalMinor, $calculatedSubtotalMinor);
                $this->assertMatchesComputed('tax_amount_minor', $data->taxAmountMinor, $calculatedTaxMinor);
                $this->assertMatchesComputed(
                    'total_minor',
                    $data->totalMinor,
                    max(0, $calculatedSubtotalMinor + $calculatedTaxMinor - $discountAmountMinor)
                );
            }

            $subtotalMinor = $data->subtotalMinor ?? $calculatedSubtotalMinor;
            $taxAmountMinor = $data->taxAmountMinor ?? $this->applyBasisPoints($subtotalMinor, $taxRateBasisPoints);
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

            try {
                $doc = new Doc($docData);
                $doc->save();
            } catch (QueryException $exception) {
                throw $this->translateDocNumberViolation($exception, $docNumber);
            }

            $this->createVersion($doc, 'Initial creation');

            // Load relationships
            $doc->loadMissing(['template', 'docable']);

            // Generate PDF if requested
            if ($data->generatePdf ?? false) {
                $this->generatePdf($doc);
            }

            return $doc;
        });
    }

    /**
     * Create a new document from array data with DocType enum.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromType(DocType $type, array $data, ?Model $owner = null): Doc
    {
        return OwnerContext::withOwner($owner, function () use ($type, $data, $owner): Doc {
            return DB::transaction(function () use ($type, $data, $owner): Doc {
                // Generate document number
                $docNumber = $this->sequenceManager->generate($type, $owner);
                $this->assertDocNumberAvailable($docNumber, $owner);

                $this->assertNoMajorUnitKeys($data);

                $docData = array_merge($data, [
                    'doc_number' => $docNumber,
                    'doc_type' => $type->value,
                    'status' => Draft::class,
                    'issue_date' => $data['issue_date'] ?? CarbonImmutable::now(),
                ]);

                // Calculate totals if items provided
                if (isset($data['items'])) {
                    if (! is_array($data['items'])) {
                        throw new InvalidArgumentException('Document field `items` must be an array.');
                    }

                    $discountAmountMinor = $data['discount_amount_minor'] ?? 0;

                    if (! is_int($discountAmountMinor) || $discountAmountMinor < 0) {
                        throw new InvalidArgumentException('Document field `discount_amount_minor` must be a non-negative integer.');
                    }

                    $totals = $this->calculateTotals(
                        $data['items'],
                        $discountAmountMinor,
                        isset($data['currency']) ? (string) $data['currency'] : null
                    );
                    $docData = array_merge($docData, $totals);
                } else {
                    $this->assertValidStoredTotals($docData);
                }

                if (isset($docData['currency'])) {
                    $docData['currency'] = $this->normalizeCurrency($docData['currency']);
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

                try {
                    $doc = new Doc($docData);
                    $doc->save();
                } catch (QueryException $exception) {
                    throw $this->translateDocNumberViolation($exception, $docNumber);
                }

                // Create initial version
                $this->createVersion($doc, 'Initial creation');

                return $doc;
            });
        });
    }

    /**
     * Update a document and create a version snapshot.
     *
     * Only the updatable attribute allowlist is persisted: `doc_number`,
     * `doc_type`, `pdf_path`, lifecycle timestamps, and derived totals are
     * immutable here. Status changes go through the state machine.
     * `body` accepts a Tiptap JSON array or a RichEditor HTML string, which
     * is normalized to the stored array shape.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Doc $doc, array $data): Doc
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(
                Doc::class,
                (string) $doc->getKey(),
                owner: $this->resolveOwner(),
                includeGlobal: (bool) config('docs.owner.include_global', false),
                message: 'Document is not available in the current owner scope.',
            );
        }

        return DB::transaction(function () use ($doc, $data): Doc {
            $attributes = $this->filterUpdatableAttributes($data);

            if (array_key_exists('body', $attributes)) {
                $attributes['body'] = self::normalizeBody($attributes['body']);
            }

            $statusChange = $this->extractStatusChange($data, $doc);

            // An explicit slug wins over any stored or passed template id.
            $slugProvided = array_key_exists('template_slug', $attributes) && filled($attributes['template_slug']);

            $templateId = $slugProvided
                ? null
                : (array_key_exists('doc_template_id', $attributes)
                    ? (filled($attributes['doc_template_id']) ? (string) $attributes['doc_template_id'] : null)
                    : $doc->doc_template_id);

            $template = $this->resolveTemplateSelection(
                $doc->doc_type,
                $templateId,
                $slugProvided ? (string) $attributes['template_slug'] : null,
            );

            unset($attributes['template_slug']);

            if ($slugProvided && $template instanceof DocTemplate) {
                $attributes['doc_template_id'] = $template->id;
            }

            if ($template instanceof DocTemplate) {
                app(DocRenderService::class)->validateDocPayload(
                    $template,
                    $attributes['body'] ?? self::normalizeBody($doc->body),
                    $attributes['items'] ?? ($doc->items ?? []),
                );
            }

            // Totals are derived: recompute from items, or re-derive the total
            // when only the discount changed.
            if (array_key_exists('items', $attributes)) {
                $discount = $attributes['discount_amount_minor'] ?? $doc->discount_amount_minor;
                $totals = $this->calculateTotals(
                    $attributes['items'],
                    $discount,
                    $attributes['currency'] ?? $doc->currency
                );
                $attributes = array_merge($attributes, $totals);
            } elseif (array_key_exists('discount_amount_minor', $attributes)) {
                $attributes['total_minor'] = max(
                    0,
                    $doc->subtotal_minor + $doc->tax_amount_minor - $attributes['discount_amount_minor']
                );
            }

            if (array_key_exists('pdf_options', $attributes)) {
                $pdfOptions = $attributes['pdf_options'];
                unset($attributes['pdf_options']);

                if ($pdfOptions !== null) {
                    if (! is_array($pdfOptions)) {
                        throw new InvalidArgumentException('Document field `pdf_options` must be an array.');
                    }

                    $metadata = is_array($attributes['metadata'] ?? null)
                        ? $attributes['metadata']
                        : ($doc->metadata ?? []);
                    $metadata['pdf'] = array_merge($metadata['pdf'] ?? [], $pdfOptions);
                    $attributes['metadata'] = $metadata;
                }
            }

            if ($attributes !== []) {
                $doc->update($attributes);
            }

            if ($statusChange !== null) {
                $doc->transitionStatusTo($statusChange);
            }

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
        return $this->paymentRecorder->record($doc, $paymentData);
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
        return OwnerContext::withOwner($doc->owner, function () use ($doc, $summary): DocVersion {
            return DB::transaction(function () use ($doc, $summary): DocVersion {
                $attempts = 0;

                while (true) {
                    $attempts++;
                    $nextVersion = (int) $doc->versions()->lockForUpdate()->max('version_number') + 1;

                    try {
                        $version = $doc->versions()->make([
                            'version_number' => $nextVersion,
                            'snapshot' => $doc->toArray(),
                            'change_summary' => $summary,
                            'changed_by' => auth()->id(),
                            'created_at' => CarbonImmutable::now(),
                        ]);

                        $version->save();

                        return $version;
                    } catch (QueryException $exception) {
                        if ($attempts >= 2 || ! self::isUniqueViolation($exception)) {
                            throw $exception;
                        }
                    }
                }
            });
        });
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
        $doc->transitionStatusTo($status, $notes);
    }

    /**
     * Calculate document totals from minor-unit item values.
     *
     * Every item must use integer `quantity`, `unit_price_minor`, and optional
     * `tax_amount_minor`. Major-unit keys are rejected.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal_minor: int, tax_amount_minor: int, total_minor: int}
     */
    public function calculateTotals(array $items, int $discountAmountMinor = 0, ?string $currency = null): array
    {
        return $this->totals->calculate($items, $discountAmountMinor, $currency);
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function calculateSubtotalMinor(array $items, string $currency): int
    {
        return $this->totals->subtotal($items, $currency);
    }

    private function applyBasisPoints(int $amountMinor, int $basisPoints): int
    {
        return intdiv(($amountMinor * $basisPoints) + 5_000, 10_000);
    }

    private function resolveConfiguredTaxRateBasisPoints(): int
    {
        $taxRate = config('docs.defaults.tax_rate', 0);

        if (! is_numeric($taxRate)) {
            throw new InvalidArgumentException('docs.defaults.tax_rate must be a numeric decimal rate.');
        }

        return (int) round(((float) $taxRate) * 10_000);
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

    private function assertMatchesComputed(string $field, ?int $supplied, int $expected): void
    {
        if ($supplied !== null && $supplied !== $expected) {
            throw new InvalidArgumentException(
                "Document field `{$field}` ({$supplied}) does not match the items-derived value ({$expected})."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoMajorUnitKeys(array $data): void
    {
        foreach (['subtotal', 'total', 'tax_amount', 'discount_amount', 'tax_rate'] as $unsupportedKey) {
            if (array_key_exists($unsupportedKey, $data)) {
                throw new InvalidArgumentException(sprintf(
                    'Removed major-unit document field `%s` is not accepted; provide the corresponding minor-unit integer field.',
                    $unsupportedKey,
                ));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertValidStoredTotals(array $data): void
    {
        foreach (['subtotal_minor', 'tax_amount_minor', 'discount_amount_minor', 'total_minor'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }

            if (! is_int($data[$key]) || $data[$key] < 0) {
                throw new InvalidArgumentException("Document field `{$key}` must be a non-negative integer.");
            }
        }
    }

    private function normalizeCurrency(mixed $currency): string
    {
        if (! is_string($currency)) {
            throw new InvalidArgumentException('Document field `currency` must be a 3-letter ISO code.');
        }

        $normalized = mb_strtoupper(mb_trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Document field `currency` must be a 3-letter ISO code.');
        }

        return $normalized;
    }

    private function assertDocNumberAvailable(string $docNumber, ?Model $owner): void
    {
        $query = Doc::query()->where('doc_number', $docNumber);

        if (config('docs.owner.enabled', false)) {
            $query->forOwner($owner, false);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("Document number [{$docNumber}] is already in use.");
        }
    }

    private function translateDocNumberViolation(QueryException $exception, string $docNumber): QueryException | InvalidArgumentException
    {
        if (! self::isUniqueViolation($exception)) {
            return $exception;
        }

        return new InvalidArgumentException("Document number [{$docNumber}] is already in use.", previous: $exception);
    }

    private static function isUniqueViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterUpdatableAttributes(array $data): array
    {
        $allowed = [
            'doc_template_id',
            'template_slug',
            'docable_type',
            'docable_id',
            'issue_date',
            'due_date',
            'body',
            'notes',
            'terms',
            'customer_data',
            'company_data',
            'items',
            'metadata',
            'currency',
            'discount_amount_minor',
            'pdf_options',
        ];

        $attributes = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('items', $attributes) && ! is_array($attributes['items'])) {
            throw new InvalidArgumentException('Document field `items` must be an array.');
        }

        if (array_key_exists('discount_amount_minor', $attributes)
            && (! is_int($attributes['discount_amount_minor']) || $attributes['discount_amount_minor'] < 0)) {
            throw new InvalidArgumentException('Document field `discount_amount_minor` must be a non-negative integer.');
        }

        if (array_key_exists('currency', $attributes)) {
            $attributes['currency'] = $this->normalizeCurrency($attributes['currency']);
        }

        foreach (['metadata', 'customer_data', 'company_data'] as $arrayKey) {
            if (array_key_exists($arrayKey, $attributes)
                && $attributes[$arrayKey] !== null
                && ! is_array($attributes[$arrayKey])) {
                throw new InvalidArgumentException("Document field `{$arrayKey}` must be an array.");
            }
        }

        if (array_key_exists('body', $attributes)
            && $attributes['body'] !== null
            && ! is_array($attributes['body'])
            && ! is_string($attributes['body'])) {
            throw new InvalidArgumentException('Document field `body` must be an array or string.');
        }

        foreach (['notes', 'terms'] as $textKey) {
            if (array_key_exists($textKey, $attributes)
                && $attributes[$textKey] !== null
                && ! is_string($attributes[$textKey])) {
                throw new InvalidArgumentException("Document field `{$textKey}` must be a string.");
            }
        }

        return $attributes;
    }

    /**
     * Normalize a submitted body to the stored array shape.
     *
     * RichEditor HTML submits arrive as strings; wrap them as a single
     * Tiptap paragraph so the `body` array cast stays valid.
     *
     * @return array<string, mixed>|null
     */
    private static function normalizeBody(mixed $body): ?array
    {
        if ($body === null || is_array($body)) {
            return $body;
        }

        if (! is_string($body) || mb_trim($body) === '') {
            return null;
        }

        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $body],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return class-string<DocStatus>|null
     */
    private function extractStatusChange(array $data, Doc $doc): ?string
    {
        if (! array_key_exists('status', $data) || $data['status'] === null) {
            return null;
        }

        $statusClass = DocStatus::resolveStateClassFor($data['status']);

        if ($doc->status->equals($statusClass)) {
            return null;
        }

        return $statusClass;
    }

    protected function resolveStorageDisk(string $docType): string
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

    protected function resolveDefault(string $docType, string $key, mixed $fallback = null): mixed
    {
        $typeKey = DocTypeKey::sanitize($docType);

        if ($typeKey !== null) {
            $value = config("docs.types.{$typeKey}.defaults.{$key}");

            if ($value !== null) {
                return $value;
            }
        }

        return config("docs.defaults.{$key}", $fallback);
    }

    protected function resolveTemplateSelection(string $docType, ?string $templateId = null, ?string $templateSlug = null): ?DocTemplate
    {
        $query = $this->getTemplateQuery()->where('doc_type', $docType);

        if ($templateId !== null && $templateId !== '') {
            $template = config('docs.owner.enabled', false)
                ? OwnerWriteGuard::findOrFailForOwner(
                    DocTemplate::class,
                    $templateId,
                    owner: $this->resolveOwner(),
                    includeGlobal: (bool) config('docs.owner.include_global', false),
                    message: 'Document template is not available in the current owner scope.',
                )
                : $query->find($templateId);

            if (! $template instanceof DocTemplate || $template->doc_type !== $docType) {
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
