<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Database\Factories\ContactMethodFactory;
use AIArmada\Contacting\Support\ContactingModelReferenceGuard;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    protected $fillable = [
        'contactable_type',
        'contactable_id',
        'type',
        'purpose',
        'label',
        'value',
        'normalized_value',
        'display_value',
        'country_code',
        'is_primary',
        'is_public',
        'is_verified',
        'verified_at',
        'valid_from',
        'valid_until',
        'sort_order',
        'metadata',
    ];

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
    }

    /**
     * Save a primary contact method while serializing primary replacement per
     * contactable scope. The partial unique is the database-level backstop.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $needsPrimarySync = $this->is_primary
            && (! $this->exists
                || $this->isDirty('is_primary')
                || $this->isDirty('contactable_type')
                || $this->isDirty('contactable_id')
                || $this->isDirty('type')
                || $this->isDirty('purpose')
                || $this->isDirty('owner_type')
                || $this->isDirty('owner_id'));

        if (! $needsPrimarySync) {
            return parent::save($options);
        }

        $this->guardAllowedType();
        $this->guardContactableOwner();
        $this->prepareOwnerForPrimarySync();

        return DB::transaction(function () use ($options): bool {
            $this->syncSiblingPrimaryFlags();

            return parent::save($options);
        });
    }

    private function applyDefaultFlags(): void
    {
        if ($this->is_public === null) {
            $this->is_public = match (mb_strtolower((string) $this->type)) {
                'email', 'phone', 'mobile', 'whatsapp', 'fax' => false,
                default => (bool) config('contacting.defaults.public_by_default', true),
            };
        }

        if ($this->is_verified === null) {
            $this->is_verified = (bool) config('contacting.defaults.verified_by_default', false);
        }
    }

    private function normalizeForSave(): void
    {
        $displayValueWasExplicitlyChanged = $this->isDirty('display_value');
        $contactValueChanged = $this->isDirty(['value', 'country_code']);

        if ($this->country_code !== null) {
            $this->country_code = mb_strtoupper($this->country_code);
        }

        $normalized = app(NormalizeContactMethodAction::class)->execute(
            $this->type,
            $this->value,
            $this->country_code,
        );

        $this->normalized_value = $normalized['normalized_value'];

        if (! $displayValueWasExplicitlyChanged
            && (! $this->exists || $contactValueChanged || $this->display_value === null)) {
            $this->display_value = $normalized['display_value'];
        }
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

        if ($this->contactable_type === null || $this->contactable_id === null) {
            return;
        }

        $this->contactable()->lockForUpdate()->firstOrFail();

        // The surrounding save transaction rolls this demotion back if a
        // later model guard or the database constraint rejects the write.
        ContactMethod::query()
            ->where('contactable_type', $this->contactable_type)
            ->where('contactable_id', $this->contactable_id)
            ->where('type', $this->type)
            ->where('purpose', $this->purpose)
            ->where('id', '!=', $this->id)
            ->where(function (Builder $query): void {
                if ($this->owner_type === null) {
                    $query->whereNull('owner_type')->whereNull('owner_id');

                    return;
                }

                $query->where('owner_type', $this->owner_type)
                    ->where('owner_id', $this->owner_id);
            })
            ->lockForUpdate()
            ->update(['is_primary' => false]);
    }

    private function prepareOwnerForPrimarySync(): void
    {
        if ($this->exists) {
            return;
        }

        $config = static::resolveOwnerScopeConfig();

        if ($config->enabled) {
            static::assignOwnerOnCreate($this, $config);
        }
    }

    private function guardContactableOwner(): void
    {
        app(ContactingModelReferenceGuard::class)->resolve(
            $this->contactable_type,
            $this->contactable_id,
        );
    }

    protected static function newFactory(): ContactMethodFactory
    {
        return ContactMethodFactory::new();
    }
}
