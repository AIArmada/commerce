<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Models\Concerns\ScopesByProgramOwner;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string|null $program_id
 * @property string $type
 * @property string $name
 * @property string|null $description
 * @property int|null $width
 * @property int|null $height
 * @property string $asset_url
 * @property string $destination_url
 * @property string $tracking_code
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram|null $program
 */
class AffiliateProgramCreative extends Model implements Auditable, HasMedia
{
    use HasCommerceAudit;
    use HasUuids;
    use InteractsWithMedia;
    use LogsCommerceActivity;
    use ScopesByProgramOwner;

    protected $fillable = [
        'program_id',
        'type',
        'name',
        'description',
        'width',
        'height',
        'asset_url',
        'destination_url',
        'tracking_code',
        'metadata',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.program_creatives', 'affiliate_program_creatives');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>|BelongsTo<Model, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    /**
     * Scope to general creatives not tied to a program.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('program_id');
    }

    /**
     * Scope to creatives belonging to a specific program.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProgram(Builder $query, AffiliateProgram | string $program): Builder
    {
        $programId = $program instanceof AffiliateProgram ? $program->getKey() : $program;

        return $query->where('program_id', $programId);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('creative_asset')
            ->useDisk('public')
            ->singleFile()
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
                'video/mp4',
                'video/webm',
                'application/pdf',
                'application/zip',
            ]);
    }

    public function getTrackingUrl(Affiliate $affiliate): string
    {
        $baseUrl = $this->destination_url;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $param = config('affiliates.links.parameter', 'aff');

        return $baseUrl . $separator . $param . '=' . $affiliate->code;
    }

    public function getEmbedCode(Affiliate $affiliate): string
    {
        $trackingUrl = $this->getTrackingUrl($affiliate);

        return match ($this->type) {
            'banner' => sprintf(
                '<a href="%s" target="_blank"><img src="%s" width="%d" height="%d" alt="%s" /></a>',
                $trackingUrl,
                $this->asset_url,
                $this->width ?? 0,
                $this->height ?? 0,
                htmlspecialchars($this->name)
            ),
            'text_link' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                $trackingUrl,
                htmlspecialchars($this->name)
            ),
            default => $trackingUrl,
        };
    }

    public function getDimensions(): ?string
    {
        if ($this->width && $this->height) {
            return "{$this->width}x{$this->height}";
        }

        return null;
    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
