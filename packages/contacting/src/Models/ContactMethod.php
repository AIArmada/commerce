<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Database\Factories\ContactMethodFactory;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

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
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'contacting.features.owner';

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

    protected static function booted(): void
    {
        static::creating(function (ContactMethod $contactMethod): void {
            $contactMethod->applyDefaultFlags();
        });

        static::saving(function (ContactMethod $contactMethod): void {
            $contactMethod->normalizeForSave();
            $contactMethod->guardAllowedType();
            $contactMethod->guardContactableOwner();
        });

        static::saved(function (ContactMethod $contactMethod): void {
            $contactMethod->syncSiblingPrimaryFlags();
        });
    }

    private function applyDefaultFlags(): void
    {
        if ($this->is_public === null) {
            $this->is_public = (bool) config('contacting.defaults.public_by_default', true);
        }

        if ($this->is_verified === null) {
            $this->is_verified = (bool) config('contacting.defaults.verified_by_default', false);
        }
    }

    private function normalizeForSave(): void
    {
        if ($this->country_code !== null) {
            $this->country_code = mb_strtoupper($this->country_code);
        }

        $normalized = app(NormalizeContactMethodAction::class)->execute(
            $this->type,
            $this->value,
            $this->country_code,
        );

        $this->normalized_value = $normalized['normalized_value'];
        $this->display_value = $normalized['display_value'];
    }

    private function guardAllowedType(): void
    {
        if (! (bool) config('contacting.features.strict_contact_types', false)) {
            return;
        }

        $allowedTypes = config('contacting.contact_methods.types', []);
        $allowedValues = array_is_list($allowedTypes) ? $allowedTypes : array_keys($allowedTypes);

        if (! in_array($this->type, $allowedValues, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported contact method type "%s".', $this->type));
        }
    }

    private function syncSiblingPrimaryFlags(): void
    {
        if (! $this->is_primary) {
            return;
        }

        ContactMethod::query()
            ->where('contactable_type', $this->contactable_type)
            ->where('contactable_id', $this->contactable_id)
            ->where('type', $this->type)
            ->where('purpose', $this->purpose)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);
    }

    private function guardContactableOwner(): void
    {
        $contactable = $this->contactable;

        if (! $contactable instanceof Model) {
            return;
        }

        $contactableOwnerType = $contactable->getAttribute('owner_type');
        $contactableOwnerId = $contactable->getAttribute('owner_id');
        $owner = OwnerContext::resolve();

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            return;
        }

        if (
            $owner !== null
            && $contactableOwnerType === $owner->getMorphClass()
            && (string) $contactableOwnerId === (string) $owner->getKey()
        ) {
            return;
        }

        if ($owner === null && $contactableOwnerType === null && $contactableOwnerId === null) {
            return;
        }

        throw new InvalidArgumentException('Contact method contactable owner must match the contact method owner.');
    }

    protected static function newFactory(): ContactMethodFactory
    {
        return ContactMethodFactory::new();
    }
}
