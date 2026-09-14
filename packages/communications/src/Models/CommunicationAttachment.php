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
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (CommunicationAttachment $attachment): void {
            self::validateStorage($attachment);
        });
    }

    private static function validateStorage(CommunicationAttachment $attachment): void
    {
        if (! is_string($attachment->filename) || mb_trim($attachment->filename) === '') {
            throw new InvalidArgumentException('Attachment filename must not be empty.');
        }

        if ($attachment->storage_disk !== null) {
            if (! is_string($attachment->storage_disk) || mb_trim($attachment->storage_disk) === '') {
                throw new InvalidArgumentException('Attachment storage disk must not be empty.');
            }

            $allowedDisks = config('communications.attachments.allowed_disks');

            if (is_array($allowedDisks) && $allowedDisks !== [] && ! in_array($attachment->storage_disk, $allowedDisks, true)) {
                throw new InvalidArgumentException(
                    "Attachment storage disk [{$attachment->storage_disk}] is not allowed.",
                );
            }
        }

        if ($attachment->storage_path !== null) {
            if (! is_string($attachment->storage_path) || mb_trim($attachment->storage_path) === '') {
                throw new InvalidArgumentException('Attachment storage path must not be empty.');
            }

            if (
                str_contains($attachment->storage_path, '..')
                || str_starts_with($attachment->storage_path, '/')
                || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $attachment->storage_path)
                || str_contains($attachment->storage_path, '://')
            ) {
                throw new InvalidArgumentException('Attachment storage path must be a relative path without traversal.');
            }
        }

        if ($attachment->mime_type !== null) {
            if (! is_string($attachment->mime_type) || preg_match('/^[\w.+-]+\/[\w.+-]+$/', $attachment->mime_type) !== 1) {
                throw new InvalidArgumentException('Attachment mime type must be a valid type/subtype value.');
            }

            $allowedMimes = config('communications.attachments.allowed_mimes');

            if (
                is_array($allowedMimes) && $allowedMimes !== []
                && ! in_array(mb_strtolower($attachment->mime_type), array_map(static fn (mixed $mime): string => mb_strtolower((string) $mime), $allowedMimes), true)
            ) {
                throw new InvalidArgumentException(
                    "Attachment mime type [{$attachment->mime_type}] is not allowed.",
                );
            }
        }

        if ($attachment->size_bytes !== null) {
            $maxSize = max(0, (int) config('communications.attachments.max_size_bytes', 10485760));

            if ($attachment->size_bytes < 0 || $attachment->size_bytes > $maxSize) {
                throw new InvalidArgumentException(
                    "Attachment size must be between 0 and {$maxSize} bytes.",
                );
            }
        }
    }
}
