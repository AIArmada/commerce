<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class RewardLedgerEntries
{
    /**
     * @var list<string>
     */
    public const array ACTOR_TYPES = ['user', 'system'];

    /**
     * @var list<string>
     */
    public const array ACTOR_ROLES = ['member', 'staff', 'admin', 'system'];

    /**
     * Allowlisted recipient/member morph types from `rewards.recipient_types`.
     * Empty by default: the host must opt in, and evaluation refuses every
     * recipient type until it does.
     *
     * @return list<string>
     */
    public static function recipientTypes(): array
    {
        $configured = config('rewards.recipient_types', []);

        return is_array($configured) ? array_values($configured) : [];
    }

    public static function assertActor(string $actorType, ?string $actorId, string $actorRole): void
    {
        if (! in_array($actorType, self::ACTOR_TYPES, true)) {
            throw new InvalidArgumentException('Actor type must be one of [' . implode(',', self::ACTOR_TYPES) . '].');
        }

        if (! in_array($actorRole, self::ACTOR_ROLES, true)) {
            throw new InvalidArgumentException('Actor role must be one of [' . implode(',', self::ACTOR_ROLES) . '].');
        }

        if ($actorType === 'system' && $actorId !== null) {
            throw new InvalidArgumentException('System actors must not carry an actor id.');
        }

        if ($actorType === 'user' && ($actorId === null || ! Str::isUuid($actorId))) {
            throw new InvalidArgumentException('User actors require an explicit UUID actor id.');
        }

        if ($actorType === 'user' && $actorRole === 'system') {
            throw new InvalidArgumentException('User actors must not use the system role.');
        }

        if ($actorType === 'system' && $actorRole !== 'system') {
            throw new InvalidArgumentException('System actors must use the system role.');
        }
    }

    public static function assertReason(string $reason): void
    {
        if ($reason === '' || mb_strlen($reason) > 191) {
            throw new InvalidArgumentException('Reason must be a non-empty string up to 191 characters.');
        }
    }
}
