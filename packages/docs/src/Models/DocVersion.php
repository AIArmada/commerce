<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Services\DocService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Document version for tracking changes over time.
 *
 * @property string $id
 * @property string $doc_id
 * @property int $version_number
 * @property array<string, mixed> $snapshot
 * @property string|null $change_summary
 * @property string|null $changed_by
 * @property CarbonImmutable $created_at
 * @property-read Doc $doc
 */
final class DocVersion extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    public $timestamps = false;

    protected $fillable = [
        'doc_id',
        'version_number',
        'snapshot',
        'change_summary',
        'changed_by',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            if ($version->created_at === null) {
                $version->created_at = CarbonImmutable::now();
            }
        });
    }

    public function getTable(): string
    {
        return config('docs.database.tables.doc_versions', 'docs_versions');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    /**
     * Restore this version to the document.
     *
     * The snapshot is applied through the document service so template
     * validation, totals derivation, the status state machine, and owner
     * scoping all apply; the restore itself is recorded as a new version.
     * Immutable snapshot keys (number, type, pdf path, totals) are ignored.
     */
    public function restore(?string $summary = null): void
    {
        $doc = $this->doc;

        if (! $doc instanceof Doc) {
            throw (new ModelNotFoundException)->setModel(Doc::class, [$this->doc_id]);
        }

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(
                Doc::class,
                (string) $doc->getKey(),
                owner: OwnerContext::resolve(),
                includeGlobal: (bool) config('docs.owner.include_global', false),
                message: 'Document is not available in the current owner scope.',
            );
        }

        $restored = app(DocService::class)->update($doc, $this->snapshot ?? []);

        $restored->versions()->latest('version_number')->first()
            ?->update(['change_summary' => $summary ?? "Restored version {$this->version_number}"]);
    }

    /**
     * Get the diff between this version and another.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(?self $other = null): array
    {
        $otherSnapshot = $other?->snapshot ?? [];
        $diff = [];

        foreach ($this->snapshot as $key => $value) {
            if (! array_key_exists($key, $otherSnapshot) || $otherSnapshot[$key] !== $value) {
                $diff[$key] = [
                    'old' => $otherSnapshot[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $diff;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
