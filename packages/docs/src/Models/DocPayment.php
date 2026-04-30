<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks payments made against documents.
 *
 * @property string $id
 * @property string $doc_id
 * @property string $amount
 * @property string $currency
 * @property string $payment_method
 * @property string|null $reference
 * @property string|null $transaction_id
 * @property CarbonImmutable $paid_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Doc $doc
 */
final class DocPayment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_id',
        'amount',
        'currency',
        'payment_method',
        'reference',
        'transaction_id',
        'paid_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_payments', 'docs_payments');
    }

    protected static function booted(): void
    {
        static::creating(function (DocPayment $payment): void {
            if ($payment->owner_type !== null && $payment->owner_id !== null) {
                return;
            }

            if ($payment->doc_id === null || $payment->doc_id === '') {
                return;
            }

            $doc = Doc::query()
                ->withoutOwnerScope()
                ->whereKey($payment->doc_id)
                ->first();

            if ($doc === null) {
                return;
            }

            $payment->owner_type = $doc->owner_type;
            $payment->owner_id = $doc->owner_id;
        });
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
