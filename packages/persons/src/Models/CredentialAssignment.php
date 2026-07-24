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

/**
 * @property string $id
 * @property string $credentialable_type
 * @property string $credentialable_id
 * @property string $credential_id
 * @property string|null $issuing_institution_id
 * @property string|null $registration_number
 * @property CarbonImmutable|null $date_obtained
 * @property CarbonImmutable|null $date_expired
 * @property AssignmentStatus $status
 * @property-read Model $credentialable
 * @property-read CredentialDefinition $credential
 */
class CredentialAssignment extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'credentialable_type',
        'credentialable_id',
        'credential_id',
        'issuing_institution_id',
        'registration_number',
        'date_obtained',
        'date_expired',
        'status',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.credential_assignments', 'credential_assignments');
    }

    protected function casts(): array
    {
        return [
            'date_obtained' => 'immutable_date',
            'date_expired' => 'immutable_date',
            'status' => AssignmentStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function credentialable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<CredentialDefinition, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(CredentialDefinition::class, 'credential_id');
    }
}
