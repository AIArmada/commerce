<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Pre-defined commission structures that can be applied to affiliates or programs.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_default
 * @property bool $is_active
 * @property array<string, mixed> $rules
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class AffiliateCommissionTemplate extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_default',
        'is_active',
        'rules',
        'metadata',
    ];

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }

    /**
     * Get the default template.
     */
    public static function getDefault(): ?self
    {
        return self::query()->active()->default()->first();
    }

    /**
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Get commission rules as structured array.
     *
     * @return array<int, array{
     *     type: string,
     *     commission_type: string,
     *     rate: int,
     *     conditions: array<string, mixed>
     * }>
     */
    public function getCommissionRules(): array
    {
        return $this->rules['commission_rules'] ?? [];
    }

    /**
     * Get volume tiers from template.
     *
     * @return array<int, array{
     *     min_volume: int,
     *     max_volume: int|null,
     *     bonus_rate: int
     * }>
     */
    public function getVolumeTiers(): array
    {
        return $this->rules['volume_tiers'] ?? [];
    }

    /**
     * Get MLM override percentages from template.
     *
     * @return array<int, int>
     */
    public function getMlmOverrides(): array
    {
        return $this->rules['mlm_overrides'] ?? [];
    }

    public function getTable(): string
    {
        return config('affiliates.database.tables.commission_templates', 'affiliate_commission_templates');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        return $this->baseScopeForOwner($query, $owner, $includeGlobal);
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }

            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($template->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $template->owner_type = $owner->getMorphClass();
                $template->owner_id = $owner->getKey();
            }
        });

    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'rules' => 'array',
            'metadata' => 'array',
        ];
    }
}
