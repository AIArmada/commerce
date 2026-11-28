<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Contracts\DocumentPdfCleanup;
use AIArmada\Docs\Runtime\ApprovalRelationModel;
use AIArmada\Docs\Runtime\DeliveryRelationModels;
use AIArmada\Docs\Runtime\LifecycleRelationModels;
use AIArmada\Docs\Runtime\PaymentRelationModel;
use AIArmada\Docs\Runtime\TemplateRelationModel;
use AIArmada\Docs\Runtime\VersionRelationModel;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Refunded;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $doc_number
 * @property string $doc_type
 * @property string|null $doc_template_id
 * @property string|null $docable_type
 * @property string|null $docable_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property DocStatus $status
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $refunded_at
 * @property CarbonImmutable|null $overdue_at
 * @property int $subtotal_minor
 * @property int $tax_amount_minor
 * @property int $discount_amount_minor
 * @property int $total_minor
 * @property string $currency
 * @property array<string, mixed>|null $body
 * @property string|null $notes
 * @property string|null $terms
 * @property array<string, mixed>|null $customer_data
 * @property array<string, mixed>|null $company_data
 * @property array<int, array<string, mixed>>|null $items
 * @property array<string, mixed>|null $metadata
 * @property string|null $pdf_path
 * @property string|null $pdf_fingerprint
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read RelatedDocTemplate|null $template
 * @phpstan-type RelatedDocTemplate \AIArmada\Docs\Features\Templates\Models\DocTemplate
 * @property-read Collection<int, RelatedDocStatusHistory> $statusHistories
 * @phpstan-type RelatedDocStatusHistory \AIArmada\Docs\Features\Lifecycle\Models\DocStatusHistory
 * @property-read Collection<int, RelatedDocPayment> $payments
 * @phpstan-type RelatedDocPayment \AIArmada\Docs\Features\Payments\Models\DocPayment
 * @property-read Collection<int, RelatedDocVersion> $versions
 * @phpstan-type RelatedDocVersion \AIArmada\Docs\Features\Versioning\Models\DocVersion
 * @property-read Collection<int, RelatedDocEmail> $emails
 * @phpstan-type RelatedDocEmail \AIArmada\Docs\Features\Delivery\Models\DocEmail
 * @property-read Collection<int, RelatedDocApproval> $approvals
 * @phpstan-type RelatedDocApproval \AIArmada\Docs\Features\Approvals\Models\DocApproval
 * @property-read Collection<int, RelatedDocShareLink> $shareLinks
 * @phpstan-type RelatedDocShareLink \AIArmada\Docs\Features\Delivery\Models\DocShareLink
 * @property-read RelatedDocEInvoiceSubmission|null $eInvoiceSubmission
 * @phpstan-type RelatedDocEInvoiceSubmission \AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission
 * @property-read Model|null $docable
 */
final class Doc extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasStates;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_number',
        'doc_type',
        'doc_template_id',
        'docable_type',
        'docable_id',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'sent_at',
        'cancelled_at',
        'refunded_at',
        'overdue_at',
        'subtotal_minor',
        'tax_amount_minor',
        'discount_amount_minor',
        'total_minor',
        'currency',
        'body',
        'notes',
        'terms',
        'customer_data',
        'company_data',
        'items',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.docs', 'docs');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function docable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<RelatedDocTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(app(TemplateRelationModel::class)->template(), 'doc_template_id');
    }

    /**
     * @return HasMany<RelatedDocStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(app(LifecycleRelationModels::class)->statusHistory(), 'doc_id');
    }

    /**
     * @return HasMany<RelatedDocPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(app(PaymentRelationModel::class)->payment(), 'doc_id');
    }

    /**
     * @return HasMany<RelatedDocVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(app(VersionRelationModel::class)->version(), 'doc_id');
    }

    /**
     * @return HasMany<RelatedDocEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(app(DeliveryRelationModels::class)->email(), 'doc_id');
    }

    /**
     * @return HasMany<RelatedDocApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(app(ApprovalRelationModel::class)->approval(), 'doc_id');
    }

    /**
     * @return HasMany<RelatedDocShareLink, $this>
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(app(DeliveryRelationModels::class)->shareLink(), 'doc_id');
    }

    /**
     * @return HasOne<RelatedDocEInvoiceSubmission, $this>
     */
    public function eInvoiceSubmission(): HasOne
    {
        return $this->hasOne(app(LifecycleRelationModels::class)->eInvoiceSubmission(), 'doc_id');
    }

    public function isOverdue(): bool
    {
        if ($this->status->equals(Paid::class) || $this->status->equals(Cancelled::class) || $this->status->equals(Refunded::class)) {
            return false;
        }

        return $this->due_date !== null && $this->due_date->isPast();
    }

    public function isPaid(): bool
    {
        return $this->status->equals(Paid::class);
    }

    public function canBePaid(): bool
    {
        return $this->status->isPayable();
    }

    public function hasFinancialEvidence(): bool
    {
        return $this->isPaid()
            || $this->status->equals(PartiallyPaid::class)
            || $this->status->equals(Refunded::class)
            || $this->paid_at !== null
            || $this->payments()->withoutOwnerScope()->exists()
            || $this->eInvoiceSubmission()->withoutOwnerScope()->exists();
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            if ($this->exists) {
                $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
                $this->refresh();
            }

            return parent::delete();
        });
    }

    protected static function booted(): void
    {
        self::deleting(function (Doc $doc): void {
            if ($doc->hasFinancialEvidence()) {
                throw new LogicException('Documents with payment or e-invoice evidence cannot be deleted.');
            }

            $id = (string) $doc->getKey();
            $type = $doc->doc_type;
            $path = $doc->pdf_path;
            $doc->getConnection()->afterCommit(static fn () => app(DocumentPdfCleanup::class)->execute($id, $type, $path));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DocStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
            'overdue_at' => 'immutable_datetime',
            'subtotal_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'body' => 'array',
            'customer_data' => 'array',
            'company_data' => 'array',
            'items' => 'array',
            'metadata' => 'array',
        ];
    }
}
