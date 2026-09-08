<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\AssignmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $titleable_type
 * @property string $titleable_id
 * @property string $title_id
 * @property string|null $issuer_id
 * @property CarbonImmutable|null $date_awarded
 * @property CarbonImmutable|null $date_expired
 * @property AssignmentStatus $status
 * @property-read Model $titleable
 * @property-read Title $title
 */
class TitleAssignment extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'titleable_type',
        'titleable_id',
        'title_id',
        'issuer_id',
        'date_awarded',
        'date_expired',
        'status',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.title_assignments', 'title_assignments');
    }

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'date_awarded' => 'immutable_date',
            'date_expired' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (TitleAssignment $assignment): void {
            if (! Title::query()->whereKey($assignment->getAttribute('title_id'))->exists()) {
                throw new InvalidArgumentException('The title assignment must reference a persisted title.');
            }

            $issuerId = $assignment->getAttribute('issuer_id');

            if ($issuerId !== null && ! TitleIssuer::query()->whereKey($issuerId)->exists()) {
                throw new InvalidArgumentException('The title assignment issuer must reference a persisted title issuer.');
            }
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function titleable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Title, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class, 'title_id');
    }

    /**
     * @return BelongsTo<TitleIssuer, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(TitleIssuer::class, 'issuer_id');
    }
}
