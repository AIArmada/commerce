<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\TemplateStatus;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string $default_locale
 * @property TemplateStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $disabled_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CommunicationTemplateVersion> $versions
 * @property-read Model|Eloquent $owner
 */
final class CommunicationTemplate extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.templates', 'communication_templates');
    }

    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
            'published_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return HasMany<CommunicationTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CommunicationTemplateVersion::class, 'template_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (CommunicationTemplate $template): void {
            $template->versions()->each(fn (CommunicationTemplateVersion $v) => $v->delete());
        });
    }
}
