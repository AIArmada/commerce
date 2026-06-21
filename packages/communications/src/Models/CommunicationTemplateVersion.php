<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $template_id
 * @property int $version
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string|null $content_text
 * @property string|null $content_html
 * @property array|null $payload
 * @property array|null $variables_schema
 * @property string|null $checksum
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $superseded_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationTemplate $template
 * @property-read Model|Eloquent $owner
 */
final class CommunicationTemplateVersion extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.template_versions', 'communication_template_versions');
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'variables_schema' => 'array',
            'published_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }
}
