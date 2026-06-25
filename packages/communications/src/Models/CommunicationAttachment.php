<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Eloquent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $communication_id
 * @property string|null $content_id
 * @property string|null $attachable_type
 * @property string|null $attachable_id
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $checksum
 * @property string|null $inline_content_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Communication $communication
 * @property-read CommunicationContent|null $content
 * @property-read Model|Eloquent $attachable
 * @property-read Model|Eloquent $owner
 */
final class CommunicationAttachment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'communication_id',
        'content_id',
        'attachable_type',
        'attachable_id',
        'storage_disk',
        'storage_path',
        'filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'inline_content_id',
        'metadata',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.attachments', 'communication_attachments');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
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
     * @return BelongsTo<CommunicationContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(CommunicationContent::class, 'content_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
