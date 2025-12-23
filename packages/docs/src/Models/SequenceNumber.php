<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks the last used number for each sequence period.
 *
 * @property string $id
 * @property string $doc_sequence_id
 * @property string $period_key
 * @property int $last_number
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read DocSequence $sequence
 */
final class SequenceNumber extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'doc_sequence_id',
        'owner_type',
        'owner_id',
        'period_key',
        'last_number',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.sequence_numbers', 'docs_sequence_numbers');
    }

    /**
     * @return BelongsTo<DocSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DocSequence::class, 'doc_sequence_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
