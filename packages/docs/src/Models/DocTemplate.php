<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $view_name
 * @property string $doc_type
 * @property bool $is_default
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Collection<int, Doc> $docs
 */
final class DocTemplate extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'view_name',
        'doc_type',
        'is_default',
        'settings',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_templates', 'doc_templates');
    }

    /**
     * @return HasMany<Doc, $this>
     */
    public function docs(): HasMany
    {
        return $this->hasMany(Doc::class);
    }

    /**
     * Set this template as default within the same owner context
     */
    public function setAsDefault(): void
    {
        // Build query to remove default from other templates of the same type.
        // When owner scoping is enabled, we must not rely on ambient OwnerContext here;
        // we explicitly scope the update to this record's owner boundary.
        $query = self::query()
            ->when(
                config('docs.owner.enabled', false),
                fn (Builder $query): Builder => $query->withoutOwnerScope(),
            )
            ->whereKeyNot($this->id)
            ->where('doc_type', $this->doc_type);

        // Scope to same owner context.
        if (config('docs.owner.enabled', false)) {
            if ($this->owner_type !== null && $this->owner_id !== null) {
                $query
                    ->where('owner_type', $this->owner_type)
                    ->where('owner_id', $this->owner_id);
            } else {
                $query
                    ->whereNull('owner_type')
                    ->whereNull('owner_id');
            }
        }

        $query->update(['is_default' => false]);

        // Set this as default
        $this->update(['is_default' => true]);
    }

    protected static function booted(): void
    {
        self::deleting(function (DocTemplate $template): void {
            // Nullify the template reference on associated docs rather than deleting them
            $template->docs()->update(['doc_template_id' => null]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Scope to default templates.
     *
     * @param  Builder<DocTemplate>  $query
     * @return Builder<DocTemplate>
     */
    public function scopeDefault(Builder $query, ?string $docType = null): Builder
    {
        $query->where('is_default', true);

        if ($docType) {
            $query->where('doc_type', $docType);
        }

        return $query;
    }
}
