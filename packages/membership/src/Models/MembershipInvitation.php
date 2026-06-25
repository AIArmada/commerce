<?php

declare(strict_types=1);

namespace AIArmada\Membership\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property string $invited_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property string|null $accepted_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MembershipInvitation extends Model
{
    use HasUuids;
    use HasOwner;
    use HasOwnerScopeConfig;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected static string $ownerScopeConfigKey = 'membership.features.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    public function getTable(): string
    {
        return (string) config('membership.database.tables.invitations', 'membership_invitations');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower($value),
        );
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function inviter(): BelongsTo
    {
        /** @phpstan-ignore argument.templateType */
        return $this->belongsTo(config('auth.providers.users.model'), 'invited_by');
    }

    public function acceptor(): BelongsTo
    {
        /** @phpstan-ignore argument.templateType */
        return $this->belongsTo(config('auth.providers.users.model'), 'accepted_by');
    }

    public function revoker(): BelongsTo
    {
        /** @phpstan-ignore argument.templateType */
        return $this->belongsTo(config('auth.providers.users.model'), 'revoked_by');
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && ! $this->isExpired();
    }

    public static function tokenForStorage(string $token): string
    {
        if (! config('membership.invitations.hash_tokens', true)) {
            return $token;
        }

        return hash('sha256', $token);
    }

    public function matchesToken(string $token): bool
    {
        return hash_equals($this->token, self::tokenForStorage($token));
    }
}
