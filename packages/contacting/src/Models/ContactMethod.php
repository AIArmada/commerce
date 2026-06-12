<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Models;

use AIArmada\Contacting\Database\Factories\ContactMethodFactory;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $contactable_type
 * @property string|null $contactable_id
 * @property string $type
 * @property string $purpose
 * @property string|null $label
 * @property string $value
 * @property string|null $normalized_value
 * @property string|null $display_value
 * @property string|null $country_code
 * @property bool $is_primary
 * @property bool $is_public
 * @property bool $is_verified
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $contactable
 * @property-read Model|Eloquent $owner
 */
final class ContactMethod extends Model
{
    use HasFactory;
    use HasUuids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('contacting.database.tables.contact_methods', 'contact_methods');
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_public' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPurpose(Builder $query, string $purpose): Builder
    {
        return $query->where('purpose', $purpose);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    protected static function newFactory(): ContactMethodFactory
    {
        return ContactMethodFactory::new();
    }
}
