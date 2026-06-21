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
 * @property string $communication_id
 * @property string|null $recipient_id
 * @property string $channel
 * @property string $locale
 * @property string|null $template_id
 * @property string|null $template_version_id
 * @property string|null $subject
 * @property string|null $content_text
 * @property string|null $content_html
 * @property array|null $payload
 * @property string|null $checksum
 * @property CarbonImmutable|null $rendered_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication $communication
 * @property-read CommunicationRecipient|null $recipient
 * @property-read CommunicationTemplate|null $template
 * @property-read CommunicationTemplateVersion|null $templateVersion
 * @property-read Model|Eloquent $owner
 */
final class CommunicationContent extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $guarded = [];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.contents', 'communication_contents');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'checksum' => 'string',
            'rendered_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class, 'communication_id');
    }

    /**
     * @return BelongsTo<CommunicationRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CommunicationRecipient::class, 'recipient_id');
    }

    /**
     * @return BelongsTo<CommunicationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<CommunicationTemplateVersion, $this>
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateVersion::class, 'template_version_id');
    }
}
