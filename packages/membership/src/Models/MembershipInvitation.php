<?php

declare(strict_types=1);

namespace AIArmada\Membership\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Membership\Enums\InvitationStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;
use LogicException;

/**
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property InvitationStatus $status
 * @property string $invited_by
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $accepted_at
 * @property string|null $accepted_by
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_by
 * @property CarbonImmutable|null $last_state_change_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MembershipInvitation extends Model
{
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'email',
        'role',
        'invited_by',
    ];

    protected $hidden = [
        'token',
    ];

    protected static string $ownerScopeConfigKey = 'membership.features.owner';

    protected static bool $ownerScopeEnabledByDefault = true;

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->status ??= InvitationStatus::Pending;
            $invitation->last_state_change_at ??= CarbonImmutable::now();
        });
    }

    public function getTable(): string
    {
        return (string) config('membership.database.tables.invitations', 'membership_invitations');
    }

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
        ];
    }

    /**
     * Issue a new invitation and hash its bearer token before persistence.
     */
    public function issue(string $rawToken, ?CarbonInterface $expiresAt = null): static
    {
        if ($this->exists) {
            throw new LogicException('An existing invitation cannot be issued again.');
        }

        if (mb_trim($rawToken) === '') {
            throw new InvalidArgumentException('An invitation token is required.');
        }

        $now = CarbonImmutable::now();
        $this->token = self::tokenForStorage($rawToken);
        $this->expires_at = $expiresAt === null ? null : CarbonImmutable::createFromInterface($expiresAt);
        $this->status = InvitationStatus::Pending;
        $this->last_state_change_at = $now;

        return $this;
    }

    /**
     * Transition an invitation and record the actor and lifecycle timestamp.
     */
    public function transitionStatus(InvitationStatus | string $status, ?Model $actor = null): static
    {
        if (! $this->exists) {
            throw new LogicException('A new invitation must use issue().');
        }

        $targetStatus = $status instanceof InvitationStatus
            ? $status
            : InvitationStatus::tryFrom($status);

        if (! $targetStatus instanceof InvitationStatus) {
            throw new InvalidArgumentException(sprintf('The invitation status [%s] is invalid.', $status));
        }

        $currentStatus = $this->status;

        if (! $currentStatus instanceof InvitationStatus) {
            throw new LogicException('A persisted invitation must have a valid status before it can transition.');
        }

        if ($currentStatus === $targetStatus) {
            $now = CarbonImmutable::now();

            if ($targetStatus === InvitationStatus::Accepted && $this->accepted_at === null) {
                $this->assertActorProvided($targetStatus, $actor);
                $this->markAccepted($actor, $now);
            } elseif ($targetStatus === InvitationStatus::Revoked && $this->revoked_at === null) {
                $this->assertActorProvided($targetStatus, $actor);
                $this->markRevoked($actor, $now);
            } elseif ($targetStatus === InvitationStatus::Expired && $this->expired_at === null) {
                $this->expired_at = $now;
            }

            $this->last_state_change_at ??= $now;

            if ($this->isDirty()) {
                $this->save();
            }

            return $this;
        }

        if ($currentStatus === InvitationStatus::Pending
            && $targetStatus === InvitationStatus::Accepted
            && $this->isExpired()) {
            throw new LogicException('An expired invitation cannot be accepted.');
        }

        $allowedStatuses = match ($currentStatus) {
            InvitationStatus::Pending => [
                InvitationStatus::Accepted,
                InvitationStatus::Revoked,
                InvitationStatus::Expired,
            ],
            InvitationStatus::Accepted, InvitationStatus::Revoked, InvitationStatus::Expired => [],
        };

        if (! in_array($targetStatus, $allowedStatuses, true)) {
            throw new LogicException(sprintf(
                'The invitation cannot transition from [%s] to [%s].',
                $currentStatus->value,
                $targetStatus->value,
            ));
        }

        $this->assertActorProvided($targetStatus, $actor);

        $now = CarbonImmutable::now();
        $this->status = $targetStatus;

        if ($targetStatus === InvitationStatus::Accepted) {
            $this->markAccepted($actor, $now);
        } elseif ($targetStatus === InvitationStatus::Revoked) {
            $this->markRevoked($actor, $now);
        } elseif ($targetStatus === InvitationStatus::Expired) {
            $this->expired_at = $now;
        }

        $this->last_state_change_at = $now;
        $this->save();

        return $this;
    }

    public function expireIfDue(): bool
    {
        if ($this->status !== InvitationStatus::Pending || ! $this->isExpired()) {
            return false;
        }

        $this->transitionStatus(InvitationStatus::Expired);

        return true;
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
        return $this->status === InvitationStatus::Expired
            || ($this->status === InvitationStatus::Pending
                && $this->expires_at?->isPast() === true);
    }

    public function isAccepted(): bool
    {
        return $this->status === InvitationStatus::Accepted;
    }

    public function isRevoked(): bool
    {
        return $this->status === InvitationStatus::Revoked;
    }

    public function isValid(): bool
    {
        return $this->status === InvitationStatus::Pending
            && ! $this->isExpired();
    }

    public static function tokenForStorage(string $token): string
    {
        return hash('sha256', $token);
    }

    public function matchesToken(string $token): bool
    {
        return hash_equals((string) $this->token, self::tokenForStorage($token));
    }

    private function markAccepted(?Model $actor, CarbonImmutable $at): void
    {
        $this->accepted_at = $at;
        $this->accepted_by = $actor?->getKey();
    }

    private function markRevoked(?Model $actor, CarbonImmutable $at): void
    {
        $this->revoked_at = $at;
        $this->revoked_by = $actor?->getKey();
    }

    private function assertActorProvided(InvitationStatus $status, ?Model $actor): void
    {
        if (in_array($status, [InvitationStatus::Accepted, InvitationStatus::Revoked], true)
            && ! $actor instanceof Model) {
            throw new InvalidArgumentException(sprintf('An actor is required to mark an invitation %s.', $status->value));
        }
    }
}
