<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Enums\DocEInvoiceSubmissionStatus;
use AIArmada\Docs\Enums\DocEInvoiceValidationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-Invoice submission tracking for Malaysian MyInvois integration.
 *
 * @property string $id
 * @property string $doc_id
 * @property string $submission_uid
 * @property string|null $document_uuid
 * @property string|null $long_id
 * @property DocEInvoiceSubmissionStatus $status
 * @property DocEInvoiceValidationStatus|null $validation_status
 * @property array<string, mixed>|null $errors
 * @property array<string, mixed>|null $warnings
 * @property string|null $ubl_xml
 * @property string|null $qr_code_url
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $validated_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Doc $doc
 */
final class DocEInvoiceSubmission extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_id',
        'submission_uid',
        'document_uuid',
        'long_id',
        'status',
        'validation_status',
        'errors',
        'warnings',
        'ubl_xml',
        'qr_code_url',
        'submitted_at',
        'validated_at',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_einvoice_submissions', 'docs_einvoice_submissions');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function isPending(): bool
    {
        return $this->status === DocEInvoiceSubmissionStatus::Pending;
    }

    public function isSubmitted(): bool
    {
        return $this->status === DocEInvoiceSubmissionStatus::Submitted;
    }

    public function isValid(): bool
    {
        return $this->validation_status === DocEInvoiceValidationStatus::Valid;
    }

    public function isRejected(): bool
    {
        return $this->validation_status === DocEInvoiceValidationStatus::Invalid;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get the MyInvois portal URL for this submission.
     */
    public function getPortalUrl(): ?string
    {
        if (! $this->long_id) {
            return null;
        }

        $baseUrl = config('docs.einvoice.sandbox', true)
            ? 'https://preprod.myinvois.hasil.gov.my'
            : 'https://myinvois.hasil.gov.my';

        return $baseUrl . '/document/' . $this->long_id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocEInvoiceSubmissionStatus::class,
            'validation_status' => DocEInvoiceValidationStatus::class,
            'errors' => 'array',
            'warnings' => 'array',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }
}
