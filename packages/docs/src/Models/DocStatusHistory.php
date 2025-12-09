<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\Docs\Enums\DocStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $doc_id
 * @property DocStatus $status
 * @property string|null $notes
 * @property string|null $changed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Doc $doc
 */
final class DocStatusHistory extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'doc_id',
        'status',
        'notes',
        'changed_by',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_status_histories', 'doc_status_histories');
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
            'status' => DocStatus::class,
        ];
    }
}
