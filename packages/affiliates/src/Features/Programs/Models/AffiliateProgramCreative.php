<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

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
 * @property string|null $destination_url
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
        return $this->table ?? config('affiliates.database.tables.program_creatives', 'affiliate_program_creatives');
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
                'video/mp4',
                'video/webm',
                'application/pdf',
                'application/zip',
            ]);
    }

    public function getAssetUrl(): ?string
    {
        return $this->getFirstMedia('creative_asset')?->getFullUrl();
    }

    public function getTrackingUrl(string $affiliateCode): ?string
    {
        $destination = $this->safeDestinationUrl();

        if ($destination === null) {
            return null;
        }

        $param = (string) config('affiliates.links.parameter', 'aff');
        $fragmentParts = explode('#', $destination, 2);
        $urlParts = explode('?', $fragmentParts[0], 2);
        $fragment = $fragmentParts[1] ?? null;
        $base = $urlParts[0];
        $query = $urlParts[1] ?? null;

        $parameters = array_filter(explode('&', $query ?? ''), static function (string $pair) use ($param): bool {
            return $pair !== '' && urldecode(explode('=', $pair, 2)[0]) !== $param;
        });
        $parameters[] = rawurlencode($param) . '=' . rawurlencode($affiliateCode);

        return $base . '?' . implode('&', $parameters) . ($fragment === null ? '' : '#' . $fragment);
    }

    public function safeDestinationUrl(): ?string
    {
        $url = $this->destination_url;

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    public function getEmbedCode(string $affiliateCode): ?string
    {
        $trackingUrl = $this->getTrackingUrl($affiliateCode);

        if ($trackingUrl === null) {
            return null;
        }

        $assetUrl = $this->getAssetUrl();

        if ($this->type === 'banner' && $assetUrl === null) {
            return null;
        }

        return match ($this->type) {
            'banner' => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer"><img src="%s" width="%d" height="%d" alt="%s" /></a>',
                htmlspecialchars($trackingUrl),
                htmlspecialchars($assetUrl ?? ''),
                $this->width ?? 0,
                $this->height ?? 0,
                htmlspecialchars($this->name)
            ),
            'text_link' => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                htmlspecialchars($trackingUrl),
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
