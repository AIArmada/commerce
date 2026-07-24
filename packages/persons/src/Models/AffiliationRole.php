<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $affiliation_id
 * @property string $role_name
 * @property string|null $department
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property bool $is_current
 * @property-read Affiliation $affiliation
 */
class AffiliationRole extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'affiliation_id',
        'role_name',
        'department',
        'start_date',
        'end_date',
        'is_current',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.affiliation_roles', 'affiliation_roles');
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Affiliation, $this>
     */
    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class, 'affiliation_id');
    }
}
