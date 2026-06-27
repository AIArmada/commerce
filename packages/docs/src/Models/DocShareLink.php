<?php

declare(strict_types=1);

namespace AIArmada\Docs\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Docs\Enums\ShareLinkAction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $doc_id
 * @property string $token_hash
 * @property array<int, string> $allowed_actions
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property int $access_count
 * @property CarbonImmutable|null $last_accessed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Doc $doc
 */
final class DocShareLink extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'docs.owner';

    private ?string $plainToken = null;

    protected $fillable = [
        'doc_id',
        'token_hash',
        'allowed_actions',
        'expires_at',
        'revoked_at',
        'access_count',
        'last_accessed_at',
    ];

    public function getTable(): string
    {
        return config('docs.database.tables.doc_share_links', 'doc_share_links');
    }

    /**
     * @return BelongsTo<Doc, $this>
     */
    public function doc(): BelongsTo
    {
        return $this->belongsTo(Doc::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function allows(ShareLinkAction $action): bool
    {
        return in_array($action->value, $this->allowed_actions ?? [], true);
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => CarbonImmutable::now()]);
    }

    public function setPlainToken(string $plainToken): self
    {
        $this->plainToken = $plainToken;

        return $this;
    }

    public function plainToken(): string
    {
        if ($this->plainToken === null) {
            throw new LogicException('Plain share-link token is only available immediately after link creation.');
        }

        return $this->plainToken;
    }

    public function markAccessed(): void
    {
        $accessedAt = CarbonImmutable::now();

        self::query()
            ->withoutOwnerScope()
            ->whereKey($this->getKey())
            ->increment('access_count', 1, [
                'last_accessed_at' => $accessedAt,
            ]);

        $this->forceFill([
            'access_count' => (int) $this->access_count + 1,
            'last_accessed_at' => $accessedAt,
        ]);

        $this->syncOriginal();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_actions' => 'array',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_accessed_at' => 'immutable_datetime',
        ];
    }
}
